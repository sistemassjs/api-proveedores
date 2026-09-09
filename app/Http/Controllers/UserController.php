<?php

namespace App\Http\Controllers;

use App\Exceptions\Api\Crud\ResourceNotFoundException;
use App\Enums\EstadoUsuario;
use App\Http\Requests\User\UserStoreRequest;
use App\Http\Requests\User\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\MetricasPlataforma;
use App\Support\AdminListOrdering;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/users",
     *     summary="Listar usuarios",
     *     tags={"Usuarios"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="name", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="email", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort_by", in="query", required=false, @OA\Schema(type="string", default="name")),
     *     @OA\Parameter(name="order", in="query", required=false, @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=10)),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de usuarios",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/User")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/PaginationMeta")
     *         )
     *     )
     * )
     */
    /**
     * Conteos para segmentos del listado admin (Todos / Activos / Inactivos / Pendientes).
     * Respeta filtros del listado excepto filtros de segmento (grupo_*).
     */
    public function conteosListado(Request $request): JsonResponse
    {
        $filters = $request->only(User::getFilters());
        unset(
            $filters['grupo_activos'],
            $filters['grupo_inactivos'],
            $filters['grupo_pendientes'],
            $filters['grupo_registro_completados'],
        );

        $base = User::query()->paraListadoAdminUsuarios()->filter($filters);
        $estadoRegistroCompletado = EstadoUsuario::REGISTRO_COMPLETADO->value;

        $todos = (clone $base)->count();
        $activos = (clone $base)->where('status', true)->count();
        $inactivos = (clone $base)->where('status', false)->count();
        $pendientes = (clone $base)->whereNull('email_verified_at')->count();
        $registroCompletados = (clone $base)->whereHas('userProveedores', function ($q) use ($estadoRegistroCompletado) {
            $q->where('activo', true)->where('estado', $estadoRegistroCompletado);
        })->count();

        return $this->success([
            'todos' => $todos,
            'activos' => $activos,
            'inactivos' => $inactivos,
            'pendientes' => $pendientes,
            'registro_completados' => $registroCompletados,
        ], 'Conteos de usuarios para listado administrativo.');
    }

    public function index(Request $request)
    {
        $filters = $request->only(User::getFilters());

        $sortBy = $request->input('sort_by', 'name');
        $order = $request->input('order', 'asc');
        $perPage = min(max(1, (int) $request->input('per_page', 10)), 100);

        $query = User::query()
            ->paraListadoAdminUsuarios()
            ->with(User::eagerLodable())
            ->filter($filters);
        AdminListOrdering::applyUserStatusPriority($query);
        $originalPaginator = $query
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        $data = UserResource::collection($originalPaginator)->resolve();

        return $this->paginated($originalPaginator->setCollection(collect($data)));

        // $originalPaginator = User::with(array_merge(User::eagerLodable(), ['role']))
        //     ->filter($filters)
        //     ->orderBy($sortBy, $order)
        //     ->paginate($perPage);

        // $users = UserResource::collection($originalPaginator)->resolve();

        // return $this->paginated($originalPaginator->setCollection(collect($users)));
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     summary="Crear nuevo usuario",
     *     tags={"Usuarios"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "role_id"},
     *             @OA\Property(property="name", type="string", example="Juan Pérez"),
     *             @OA\Property(property="email", type="string", format="email", example="juan@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="role_id", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Usuario creado"),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function store(UserStoreRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role_id' => $validated['role_id'],
            'telefono' => $validated['telefono'] ?? null,
            'telefono_codigo_pais' => $validated['telefono_codigo_pais'] ?? null,
            'status' => $validated['status'] ?? EstadoUsuario::REGISTRADO->value,
        ]);

        return $this->success(
            new UserResource($user->load(User::eagerLodable())),
            'Usuario creado correctamente.',
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     summary="Obtener usuario por ID",
     *     tags={"Usuarios"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Datos del usuario", @OA\JsonContent(ref="#/components/schemas/User")),
     *     @OA\Response(response=404, description="Usuario no encontrado")
     * )
     */
    public function show($id)
    {
        $user = User::find($id);
        if (! $user) {
            throw new ResourceNotFoundException('Usuario no encontrado.');
        }

        return $this->success(new UserResource($user->load(User::eagerLodable())));
    }

    /**
     * @OA\Put(
     *     path="/api/users/{id}",
     *     summary="Actualizar usuario",
     *     tags={"Usuarios"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Usuario actualizado"),
     *     @OA\Response(response=404, description="Usuario no encontrado")
     * )
     */
    public function update(UserUpdateRequest $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validated();

        $data = collect($validated)->only([
            'name',
            'email',
            'telefono',
            'telefono_codigo_pais',
            'role_id',
            'status',
            'es_cuenta_de_pruebas',
        ])->filter(fn ($value) => $value !== null)->all();

        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        $user->update($data);

        if (array_key_exists('es_cuenta_de_pruebas', $data) || array_key_exists('role_id', $data)) {
            MetricasPlataforma::forgetCache();
        }

        return $this->success(new UserResource($user->load(['role'])));
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     summary="Eliminar usuario",
     *     tags={"Usuarios"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Usuario eliminado"),
     *     @OA\Response(response=404, description="Usuario no encontrado")
     * )
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return $this->success(null, 204);
    }
}
