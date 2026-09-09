<?php

namespace App\Http\Controllers;

use App\Exceptions\Api\Auth\UnauthorizedException;
use App\Exceptions\Api\Crud\ResourceNotFoundException;
use App\Exceptions\Api\Custom\MainUserDuplicateException;
use App\Exceptions\Api\Custom\NotFoundRelationException;
use App\Http\Requests\ProveedorUsuario\ProveedorUsuairoStoreRequest;
use App\Http\Requests\ProveedorUsuario\ProveedorUsuairoUpdateLogoRequest;
use App\Http\Requests\ProveedorUsuario\ProveedorUsuairoUpdateRequest;
use App\Http\Requests\ProveedorUsuario\ProveedorUsuarioUpdateRelacionRequest;
use App\Http\Requests\ProveedorUsuario\ProveedorUsuarioCambiarEstadoRequest;
use App\Http\Requests\ProveedorUsuario\ReasignarUsuarioRequest;
use App\Http\Resources\UserResource;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Http\Request;
use App\Support\AdminListOrdering;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProveedorUsuarioController extends Controller
{
    public function index(Request $request, Proveedor $proveedor)
    {
        $this->authorizeAccess($request->user(), $proveedor);

        $fields = User::getFilters();
        $filters = $request->only($fields);

        $sortBy = $request->input('sort_by', 'name');
        $order = $request->input('order', 'asc');
        $perPage = $request->input('per_page', 10);

        $query = $proveedor->users()
            ->with(User::eagerLodable())
            ->filter($filters);
        AdminListOrdering::applyProveedorUsuarioPriority($query);
        $usersPaginate = $query
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        $users = UserResource::collection($usersPaginate)->resolve();

        return $this->paginated(
            $usersPaginate->setCollection(collect($users)),
            'Usuarios obtenidos correctamente.'
        );
    }

    public function store(ProveedorUsuairoStoreRequest $request, Proveedor $proveedor)
    {
        $this->authorizeAccess($request->user(), $proveedor);

        $validated = $request->validated();
        $isAdmin = $request->user()->isUserAdmin();

        // Empresa MVP: siempre secundario. Admin puede crear PRINCIPAL.
        $tipoRelacion = $isAdmin
            ? ($validated['tipo_relacion'] ?? 'SECUNDARIO')
            : 'SECUNDARIO';
        $activo = array_key_exists('activo', $validated) ? (bool) $validated['activo'] : true;
        $observaciones = $validated['observaciones'] ?? null;
        $logo = $request->file('logo');

        if ($tipoRelacion === 'PRINCIPAL' && $activo) {
            $existePrincipal = $proveedor->users()
                ->wherePivot('tipo_relacion', 'PRINCIPAL')
                ->wherePivot('activo', true)
                ->exists();

            if ($existePrincipal) {
                throw new MainUserDuplicateException(
                    'Ya existe un usuario principal activo. Debe desactivarlo primero.'
                );
            }
        }

        $userId = DB::transaction(function () use ($proveedor, $validated, $tipoRelacion, $activo, $observaciones, $logo) {
            // User vive en conexión `mysql`; Proveedor en `mysql5` (misma BD).
            // El attach DEBE hacerse desde el User para compartir sesión/transacción;
            // si se hace desde Proveedor, el FK a users espera el commit → lock wait timeout.
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role_id' => $validated['role_id'],
                'telefono' => $validated['telefono'] ?? null,
                'telefono_codigo_pais' => $validated['telefono_codigo_pais'] ?? null,
            ]);

            $user->proveedores()->attach($proveedor->id, [
                'tipo_relacion' => $tipoRelacion,
                'activo' => $activo,
                'estado' => 'registrado',
                'fecha_asignacion' => now(),
                'observaciones' => $observaciones ?? "Usuario {$tipoRelacion} creado",
            ]);

            if ($logo instanceof UploadedFile) {
                $user->update(['foto_perfil_url' => $this->storeUserLogo($user, $logo)]);
            }

            return $user->id;
        });

        // Cargar fuera de la transacción: el listado vía Proveedor usa mysql5
        // y ya puede ver el user committed en mysql.
        $user = $this->loadProveedorUser($proveedor, $userId);

        return $this->success(
            new UserResource($user),
            'Usuario creado correctamente.',
            201
        );
    }

    public function show(Request $request, Proveedor $proveedor, $user)
    {
        $this->authorizeAccess($request->user(), $proveedor);
        $userModel = $this->loadProveedorUser($proveedor, $user);

        return $this->success(
            new UserResource($userModel),
            'Usuario obtenido correctamente.'
        );
    }

    public function update(ProveedorUsuairoUpdateRequest $request, Proveedor $proveedor, $user)
    {
        $this->authorizeAccess($request->user(), $proveedor);
        $userModel = $this->loadProveedorUser($proveedor, $user);
        $validated = $request->validated();
        $isPrincipal = ($userModel->pivot->tipo_relacion ?? null) === 'PRINCIPAL';
        $isAdmin = $request->user()->isUserAdmin();

        // El principal no cambia de rol ni de tipo_relación desde gestión empresa
        if ($isPrincipal && ! $isAdmin) {
            unset($validated['role_id'], $validated['tipo_relacion']);
        }

        DB::transaction(function () use ($request, $proveedor, $userModel, $validated, $isAdmin) {
            $userData = [];
            foreach (['name', 'email', 'password', 'role_id', 'telefono', 'telefono_codigo_pais'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $userData[$field] = $validated[$field] ?? null;
                }
            }

            if (array_key_exists('password', $userData) && ($userData['password'] === null || $userData['password'] === '')) {
                unset($userData['password']);
            }

            if (! empty($userData)) {
                $userModel->update($userData);
            }

            $pivotData = [];
            foreach (['tipo_relacion', 'activo', 'observaciones'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $pivotData[$field] = $validated[$field] ?? null;
                }
            }

            if (! empty($pivotData)) {
                if (
                    $isAdmin
                    && isset($pivotData['tipo_relacion'])
                    && $pivotData['tipo_relacion'] === 'PRINCIPAL'
                ) {
                    $existePrincipalOtro = $proveedor->users()
                        ->wherePivot('tipo_relacion', 'PRINCIPAL')
                        ->wherePivot('activo', true)
                        ->where('users.id', '!=', $userModel->id)
                        ->exists();

                    if ($existePrincipalOtro) {
                        throw new MainUserDuplicateException(
                            'Ya existe un usuario principal activo. Debe desactivarlo primero.'
                        );
                    }
                }

                $proveedor->users()->updateExistingPivot($userModel->id, $pivotData);
            }

            // Logo opcional: solo reemplaza si llega archivo
            if ($request->hasFile('logo')) {
                $userModel->update([
                    'foto_perfil_url' => $this->storeUserLogo($userModel, $request->file('logo')),
                ]);
            }
        });

        return $this->success(
            new UserResource($this->loadProveedorUser($proveedor, $userModel->id)),
            'Usuario actualizado correctamente.'
        );
    }

    public function destroy(Request $request, Proveedor $proveedor, $user)
    {
        $this->authorizeDelete($request->user(), $proveedor);

        $userModel = $this->resolveUserParam($user);
        $pivotData = $proveedor->users()->find($userModel->id);

        if (! $pivotData) {
            throw new NotFoundRelationException('Usuario no asociado al proveedor.');
        }

        $tipoRelacion = $pivotData->pivot->tipo_relacion ?? null;
        $isPrincipal = $tipoRelacion === 'PRINCIPAL';

        if ($isPrincipal && ! $request->user()->isUserAdmin()) {
            throw new MainUserDuplicateException('No se puede eliminar al usuario principal.');
        }

        $proveedor->users()->detach($userModel->id);
        $userModel->delete();

        return $this->success(message: 'Usuario eliminado correctamente.');
    }

    public function updateLogo(ProveedorUsuairoUpdateLogoRequest $request, Proveedor $proveedor, $user)
    {
        $this->authorizeAccess($request->user(), $proveedor);
        $userModel = $this->loadProveedorUser($proveedor, $user);

        $userModel->update([
            'foto_perfil_url' => $this->storeUserLogo($userModel, $request->file('logo')),
        ]);

        return $this->success(
            new UserResource($this->loadProveedorUser($proveedor, $userModel->id)),
            'Foto de perfil actualizada correctamente.'
        );
    }

    public function updateRelacion(ProveedorUsuarioUpdateRelacionRequest $request, Proveedor $proveedor, $user)
    {
        $this->authorizeAccess($request->user(), $proveedor);

        $userModel = $this->resolveUserParam($user);
        $pivotData = $proveedor->users()->find($userModel->id);

        if (! $pivotData) {
            throw new ResourceNotFoundException(404, 'Usuario no asociado al proveedor.');
        }

        $validated = $request->validated();
        $tipoRelacion = $validated['tipo_relacion'] ?? $pivotData->pivot->tipo_relacion;
        $activo = $validated['activo'] ?? $pivotData->pivot->activo;

        // Validar cambio a PRINCIPAL
        if (isset($validated['tipo_relacion']) && $validated['tipo_relacion'] === 'PRINCIPAL') {
            $existePrincipalOtro = $proveedor->users()
                ->wherePivot('tipo_relacion', 'PRINCIPAL')
                ->wherePivot('activo', true)
                ->where('users.id', '!=', $userModel->id)
                ->exists();

            if ($existePrincipalOtro) {
                return $this->error('Ya existe otro usuario principal activo. Debe desactivarlo o cambiarlo a secundario primero.', null, 409);
            }
        }

        // Validar que no se desactive el único usuario PRINCIPAL activo
        if (isset($validated['activo']) && ! $validated['activo']) {
            if ($pivotData->pivot->tipo_relacion === 'PRINCIPAL') {
                $countPrincipalesActivos = $proveedor->users()
                    ->wherePivot('tipo_relacion', 'PRINCIPAL')
                    ->wherePivot('activo', true)
                    ->count();

                if ($countPrincipalesActivos <= 1) {
                    return $this->error('No se puede desactivar al único usuario principal activo.', null, 409);
                }
            }
        }

        $updateData = [];
        if (isset($validated['tipo_relacion'])) {
            $updateData['tipo_relacion'] = $validated['tipo_relacion'];
        }
        if (isset($validated['activo'])) {
            $updateData['activo'] = $validated['activo'];
            if (! $validated['activo']) {
                $updateData['fecha_desasignacion'] = now();
            } else {
                $updateData['fecha_desasignacion'] = null;
            }
        }
        if (isset($validated['observaciones'])) {
            $observacionesAnteriores = $pivotData->pivot->observaciones ?? '';
            $nuevaObservacion = $validated['observaciones'];
            $timestamp = now()->format('Y-m-d H:i:s');
            $updateData['observaciones'] = $observacionesAnteriores."\n[{$timestamp}] {$nuevaObservacion}";
        }

        $proveedor->users()->updateExistingPivot($userModel->id, $updateData);

        return $this->success(
            new UserResource($this->loadProveedorUser($proveedor, $userModel->id)),
            'Relación actualizada correctamente.'
        );
    }

    public function cambiarEstado(ProveedorUsuarioCambiarEstadoRequest $request, Proveedor $proveedor, $user)
    {
        $this->authorizeAccess($request->user(), $proveedor);

        $userModel = $this->resolveUserParam($user);
        $pivotData = $proveedor->users()->find($userModel->id);

        if (! $pivotData) {
            throw new ResourceNotFoundException(404, 'Usuario no asociado al proveedor.');
        }

        $validated = $request->validated();
        $nuevoEstado = $validated['estado'];
        $observacion = $validated['observaciones'] ?? "Estado cambiado a {$nuevoEstado}";

        $observacionesAnteriores = $pivotData->pivot->observaciones ?? '';
        $timestamp = now()->format('Y-m-d H:i:s');

        $proveedor->users()->updateExistingPivot($userModel->id, [
            'estado' => $nuevoEstado,
            'observaciones' => $observacionesAnteriores."\n[{$timestamp}] {$observacion}",
        ]);

        return $this->success(
            new UserResource($this->loadProveedorUser($proveedor, $userModel->id)),
            'Estado actualizado correctamente.'
        );
    }

    /**
     * Reasigna un usuario de un proveedor origen a un proveedor destino
     * Actualiza todas las referencias en tablas relacionadas y notifica al gerente del proveedor destino
     */
    /**
     * Vincula un usuario existente (sin crear cuenta nueva) a la empresa.
     */
    public function vincularExistente(Request $request, Proveedor $proveedor)
    {
        $this->authorizeAccess($request->user(), $proveedor);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'tipo_relacion' => ['sometimes', 'string', 'in:PRINCIPAL,SECUNDARIO'],
            'activo' => ['sometimes', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        if ($proveedor->users()->where('users.id', $user->id)->exists()) {
            return $this->error('El usuario ya está vinculado a esta empresa.', null, 409);
        }

        $tipoRelacion = $validated['tipo_relacion'] ?? 'SECUNDARIO';
        $activo = $validated['activo'] ?? true;

        if ($tipoRelacion === 'PRINCIPAL' && $activo) {
            $existePrincipal = $proveedor->users()
                ->wherePivot('tipo_relacion', 'PRINCIPAL')
                ->wherePivot('activo', true)
                ->exists();

            if ($existePrincipal) {
                return $this->error('Ya existe un usuario principal activo en esta empresa.', null, 409);
            }
        }

        $observacion = $validated['observaciones']
            ?? "Usuario vinculado por administración ({$tipoRelacion})";

        $proveedor->users()->attach($user->id, [
            'tipo_relacion' => $tipoRelacion,
            'activo' => $activo,
            'estado' => 'registrado',
            'fecha_asignacion' => now(),
            'observaciones' => '[' . now()->format('Y-m-d H:i:s') . '] ' . $observacion,
        ]);

        $vinculo = $proveedor->users()->where('users.id', $user->id)->first();

        return $this->success(
            new UserResource($vinculo->load(User::eagerLodable())),
            'Usuario vinculado a la empresa correctamente.',
            201
        );
    }

    public function reasignarUsuario(ReasignarUsuarioRequest $request, $user_id)
    {
        $validated = $request->validated();

        return \DB::transaction(function () use ($user_id, $validated) {
            // 1. Obtener el usuario a reasignar
            $user = User::findOrFail($user_id);

            // 2. Validar que el usuario pertenece al proveedor origen
            $proveedorOrigen = Proveedor::findOrFail($validated['proveedor_origen_id']);
            $proveedorDestino = Proveedor::findOrFail($validated['proveedor_destino_id']);

            $relacionOrigen = $proveedorOrigen->users()->find($user->id);
            if (!$relacionOrigen) {
                throw new NotFoundRelationException('El usuario no pertenece al proveedor de origen.');
            }

            // 3. Validar restricciones de usuario PRINCIPAL
            if ($relacionOrigen->pivot->tipo_relacion === 'PRINCIPAL') {
                $countPrincipales = $proveedorOrigen->users()
                    ->wherePivot('tipo_relacion', 'PRINCIPAL')
                    ->wherePivot('activo', true)
                    ->count();

                if ($countPrincipales <= 1) {
                    return $this->error(
                        'No se puede reasignar al único usuario principal activo del proveedor de origen. Asigne otro usuario principal primero.',
                        null,
                        409
                    );
                }
            }

            // 4. Si el tipo destino es PRINCIPAL, validar que no exista otro principal activo en el destino
            if ($validated['tipo_relacion'] === 'PRINCIPAL') {
                $existePrincipalEnDestino = $proveedorDestino->users()
                    ->wherePivot('tipo_relacion', 'PRINCIPAL')
                    ->wherePivot('activo', true)
                    ->exists();

                if ($existePrincipalEnDestino) {
                    return $this->error(
                        'El proveedor de destino ya tiene un usuario principal activo.',
                        null,
                        409
                    );
                }
            }

            // 5. Actualizar referencias en tablas relacionadas
            $contadores = [
                'solicitudes_pago' => \DB::table('solicitudes_pago')
                    ->where('user_id', $user_id)
                    ->where('proveedor_id', $validated['proveedor_origen_id'])
                    ->update(['proveedor_id' => $validated['proveedor_destino_id']]),

                'ordenes_compra' => \DB::table('ordenes_compra')
                    ->where('user_id', $user_id)
                    ->where('proveedor_id', $validated['proveedor_origen_id'])
                    ->update(['proveedor_id' => $validated['proveedor_destino_id']]),

                'pedidos' => \DB::table('pedidos')
                    ->where('user_id', $user_id)
                    ->where('proveedor_id', $validated['proveedor_origen_id'])
                    ->update(['proveedor_id' => $validated['proveedor_destino_id']]),

                'oc_construcc' => \DB::table('oc_construcc')
                    ->where('proveedor_id', $validated['proveedor_origen_id'])
                    ->update(['proveedor_id' => $validated['proveedor_destino_id']]),
            ];

            // 6. Eliminar relación con proveedor origen
            $proveedorOrigen->users()->detach($user->id);

            // 7. Crear nueva relación con proveedor destino
            $observacion = $validated['observaciones'] ?? "Usuario reasignado desde {$proveedorOrigen->nombre_comercial}";
            $proveedorDestino->users()->attach($user->id, [
                'tipo_relacion' => $validated['tipo_relacion'],
                'activo' => true,
                'estado' => 'registrado',
                'fecha_asignacion' => now(),
                'observaciones' => "[" . now()->format('Y-m-d H:i:s') . "] {$observacion}",
            ]);

            // 8. Actualizar rol del usuario si es diferente
            if ($user->role_id !== $validated['role_id']) {
                $user->update(['role_id' => $validated['role_id']]);
            }

            // 9. Obtener rol para la notificación
            $rol = \App\Models\Role::find($validated['role_id']);

            // 10. Notificar al usuario principal del proveedor destino
            $usuarioPrincipalDestino = $proveedorDestino->usuarioPrincipal();
            if ($usuarioPrincipalDestino) {
                $usuarioPrincipalDestino->notify(
                    new \App\Notifications\Usuario\UsuarioReasignadoNotification(
                        $user->id,
                        $user->name,
                        $user->email,
                        $rol->name ?? 'N/A',
                        $validated['tipo_relacion'],
                        $proveedorDestino->nombre_comercial
                    )
                );
            }

            // 11. Registrar en logs
            \Log::info('Usuario reasignado', [
                'usuario_id' => $user_id,
                'proveedor_origen' => $validated['proveedor_origen_id'],
                'proveedor_destino' => $validated['proveedor_destino_id'],
                'role_id' => $validated['role_id'],
                'tipo_relacion' => $validated['tipo_relacion'],
                'registros_actualizados' => $contadores,
            ]);

            // 12. Retornar respuesta con resumen
            return $this->success([
                'usuario' => new UserResource($user->fresh()->load(User::eagerLodable())),
                'proveedor_origen' => [
                    'id' => $proveedorOrigen->id,
                    'nombre_comercial' => $proveedorOrigen->nombre_comercial,
                ],
                'proveedor_destino' => [
                    'id' => $proveedorDestino->id,
                    'nombre_comercial' => $proveedorDestino->nombre_comercial,
                ],
                'registros_actualizados' => $contadores,
            ], 'Usuario reasignado correctamente.');
        });
    }

    /**
     * CRU de usuarios del proveedor: admin, o GERENTE principal, o SUPERVISOR con acceso.
     */
    protected function authorizeAccess(User $currentUser, Proveedor $proveedor)
    {
        if ($currentUser->isUserAdmin()) {
            return;
        }

        if (! $currentUser->tieneAccesoAProveedor($proveedor->id)) {
            throw new UnauthorizedException;
        }

        $roleNombre = $currentUser->role?->nombre
            ?? $currentUser->role()->value('nombre');

        $allowed = config('proveedor_gestion_mvp.roles_gestion_usuarios_cru', ['GERENTE', 'SUPERVISOR']);

        if (! in_array($roleNombre, $allowed, true)) {
            throw new UnauthorizedException;
        }

        if ($roleNombre === 'GERENTE'
            && $currentUser->tipoRelacionConProveedor($proveedor->id) !== 'PRINCIPAL') {
            throw new UnauthorizedException;
        }
    }

    /**
     * Borrar usuarios: admin, o GERENTE principal. Supervisor no puede.
     */
    protected function authorizeDelete(User $currentUser, Proveedor $proveedor): void
    {
        if ($currentUser->isUserAdmin()) {
            return;
        }

        if (! $currentUser->tieneAccesoAProveedor($proveedor->id)) {
            throw new UnauthorizedException;
        }

        $roleNombre = $currentUser->role?->nombre
            ?? $currentUser->role()->value('nombre');

        $allowed = config('proveedor_gestion_mvp.roles_gestion_usuarios_delete', ['GERENTE']);

        if (! in_array($roleNombre, $allowed, true)) {
            throw new UnauthorizedException;
        }

        if ($currentUser->tipoRelacionConProveedor($proveedor->id) !== 'PRINCIPAL') {
            throw new UnauthorizedException;
        }
    }

    protected function resolveUserParam(mixed $user): User
    {
        if ($user instanceof User) {
            return $user;
        }

        return User::findOrFail($user);
    }

    protected function loadProveedorUser(Proveedor $proveedor, mixed $user): User
    {
        $userId = $user instanceof User ? $user->id : $user;

        $userModel = $proveedor->users()
            ->with(User::eagerLodable())
            ->where('users.id', $userId)
            ->first();

        if (! $userModel) {
            throw new ResourceNotFoundException(404, 'Usuario no asociado al proveedor.');
        }

        return $userModel;
    }

    protected function storeUserLogo(User $user, UploadedFile $file): string
    {
        $rutaAnterior = $this->relativePublicPath($user->foto_perfil_url);
        if ($rutaAnterior) {
            Storage::disk('public')->delete($rutaAnterior);
        }

        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = "logo_user_{$user->id}_".time().'.'.$extension;

        return $file->storeAs('uploads', $filename, 'public');
    }

    protected function relativePublicPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (preg_match('/^https?:\/\//', $path)) {
            $parsed = parse_url($path, PHP_URL_PATH) ?: '';
            $parsed = preg_replace('#^/storage/#', '', $parsed);

            return $parsed ? ltrim($parsed, '/') : null;
        }

        return ltrim(preg_replace('#^storage/#', '', $path) ?? $path, '/');
    }
}
