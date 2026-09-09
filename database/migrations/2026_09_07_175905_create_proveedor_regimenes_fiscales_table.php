<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('proveedor_regimenes_fiscales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')
                ->index()
                ->constrained('proveedores')
                ->restrictOnDelete();
            $table->string('clave', 10);
            $table->string('nombre', 255);
            $table->date('fecha_alta')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->boolean('es_principal')->default(false);
            $table->string('origen', 30)->nullable(); // constancia | manual
            $table->timestamps();

            $table->unique(['proveedor_id', 'clave'], 'proveedor_regimen_clave_unique');
        });

        // Migrar régimen legacy (1) a la tabla 1:N
        $rows = DB::table('proveedores')
            ->select('id', 'regimen_fiscal_clave', 'regimen_fiscal_nombre')
            ->where(function ($q) {
                $q->whereNotNull('regimen_fiscal_clave')
                    ->where('regimen_fiscal_clave', '!=', '')
                    ->where('regimen_fiscal_clave', '!=', '000');
            })
            ->orWhere(function ($q) {
                $q->whereNotNull('regimen_fiscal_nombre')
                    ->where('regimen_fiscal_nombre', '!=', '')
                    ->where('regimen_fiscal_nombre', '!=', 'Seleccione una opción');
            })
            ->get();

        $now = now();
        foreach ($rows as $row) {
            $clave = trim((string) ($row->regimen_fiscal_clave ?? ''));
            $nombre = trim((string) ($row->regimen_fiscal_nombre ?? ''));
            if ($clave === '' && $nombre === '') {
                continue;
            }
            if ($clave === '' || $clave === '000') {
                $clave = '000';
            }
            if ($nombre === '') {
                $nombre = 'Régimen fiscal';
            }

            DB::table('proveedor_regimenes_fiscales')->insert([
                'proveedor_id' => $row->id,
                'clave' => $clave,
                'nombre' => $nombre,
                'es_principal' => true,
                'origen' => 'manual',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_regimenes_fiscales');
    }
};
