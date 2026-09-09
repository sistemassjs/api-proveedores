<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigEmisorReceptorPresupuesto extends BaseModel
{
    use Filterable;

    protected $connection = 'mysql5';

    public $timestamps = true;

    protected $fillable = [
        'proveedor_id',
        'tipo',
        'subfijo',
        'nombre',
        'ape1',
        'ape2',
        'puesto',
        'telefono',
        'correo',
        'color_fondo',
        'foto_perfil',
        'file_firma',
        'estado',
        'incluir_leyenda_atentamente',
    ];

    protected $casts = [
        'proveedor_id' => 'integer',
        'tipo' => 'integer',
        'estado' => 'integer',
        'incluir_leyenda_atentamente' => 'boolean',
    ];

    const ESTADO_ACTIVO = 1;
    const ESTADO_INACTIVO = 2;
    const ESTADO_DEFAULT = 3;

    const TIPO_EMISOR = 1;
    const TIPO_RECEPTOR = 2;

    protected $hidden = ['created_at', 'updated_at'];

    protected static $filters = [
        'proveedor_id' => 'ProveedorId',
        'tipo' => 'Tipo',
        'estado' => 'Estado',
        'search' => 'Search',
    ];

    /**
     * @return array<int, string>
     */
    public static function eagerLodable(): array
    {
        return [
            'proveedor',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /**
     * Nombre completo para documento (subfijo + nombre + apellidos).
     */
    public function nombreCompletoParaDocumento(): string
    {
        $partes = array_filter([
            trim((string) ($this->subfijo ?? '')),
            trim((string) ($this->nombre ?? '')),
            trim((string) ($this->ape1 ?? '')),
            trim((string) ($this->ape2 ?? '')),
        ], static fn (string $p) => $p !== '');

        return trim(implode(' ', $partes));
    }

    /**
     * Nombre persona emisor en presupuesto (sin subfijo).
     */
    public function nombrePersonaEmisorPresupuesto(): string
    {
        $partes = array_filter([
            trim((string) ($this->nombre ?? '')),
            trim((string) ($this->ape1 ?? '')),
            trim((string) ($this->ape2 ?? '')),
        ], static fn (string $p) => $p !== '');

        return trim(implode(' ', $partes));
    }

    /**
     * @return array{nombre: ?string, puesto: ?string, telefono: ?string, correo: ?string}
     */
    public function snapshotEmisorPersona(): array
    {
        $nombre = $this->nombrePersonaEmisorPresupuesto();

        return [
            'nombre' => $nombre !== '' ? $nombre : null,
            'puesto' => $this->normalizarSnapshotTexto($this->puesto),
            'telefono' => $this->normalizarSnapshotTexto($this->telefono),
            'correo' => $this->normalizarSnapshotTexto($this->correo),
        ];
    }

    private function normalizarSnapshotTexto(?string $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }

    public function filterByProveedorId($query, $value)
    {
        return $query->whereIn('proveedor_id', explode(',', (string) $value));
    }

    public function filterByTipo($query, string $value)
    {
        $tipo = $value === 'emisor' ? self::TIPO_EMISOR : self::TIPO_RECEPTOR;

        return $query->where('tipo', $tipo);
    }

    public function filterByEstado($query, string $value)
    {
        $estado = match ($value) {
            'inactivo' => self::ESTADO_INACTIVO,
            'default' => self::ESTADO_DEFAULT,
            default => self::ESTADO_ACTIVO,
        };

        return $query->where('estado', $estado);
    }

    public function filterBySearch($query, string $value)
    {
        return $query->where(function ($q) use ($value) {
            $q->where('nombre', 'like', "%{$value}%")
                ->orWhere('ape1', 'like', "%{$value}%")
                ->orWhere('ape2', 'like', "%{$value}%")
                ->orWhere('subfijo', 'like', "%{$value}%")
                ->orWhere('puesto', 'like', "%{$value}%")
                ->orWhere('telefono', 'like', "%{$value}%")
                ->orWhere('correo', 'like', "%{$value}%");
        });
    }
}
