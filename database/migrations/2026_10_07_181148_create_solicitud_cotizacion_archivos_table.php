<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_cotizacion_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();
            $table->foreignId('solicitud_cotizacion_id')
                ->constrained('solicitud_cotizaciones')
                ->cascadeOnDelete();
            $table->foreignId('respuesta_id')
                ->nullable()
                ->constrained('solicitud_cotizacion_respuestas')
                ->nullOnDelete();
            $table->foreignId('creado_por_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('tipo', 30)->default('envio'); // envio|procesada|manual
            $table->string('nombre', 255);
            $table->string('pdf_path', 500);
            $table->timestamps();

            $table->index(['proveedor_id', 'created_at'], 'sc_archivos_proveedor_created_idx');
            $table->index(['solicitud_cotizacion_id', 'tipo'], 'sc_archivos_solicitud_tipo_idx');
        });

        // Backfill desde respuestas existentes con PDF
        if (Schema::hasTable('solicitud_cotizacion_respuestas')) {
            $rows = DB::table('solicitud_cotizacion_respuestas as r')
                ->join('solicitud_cotizaciones as s', 's.id', '=', 'r.solicitud_cotizacion_id')
                ->whereNotNull('r.pdf_path')
                ->where('r.pdf_path', '!=', '')
                ->select([
                    's.proveedor_id',
                    'r.solicitud_cotizacion_id',
                    'r.id as respuesta_id',
                    'r.enviado_por_user_id',
                    'r.pdf_path',
                    's.folio',
                    'r.created_at',
                    'r.updated_at',
                ])
                ->get();

            foreach ($rows as $row) {
                DB::table('solicitud_cotizacion_archivos')->insert([
                    'proveedor_id' => $row->proveedor_id,
                    'solicitud_cotizacion_id' => $row->solicitud_cotizacion_id,
                    'respuesta_id' => $row->respuesta_id,
                    'creado_por_user_id' => $row->enviado_por_user_id,
                    'tipo' => 'envio',
                    'nombre' => 'Cotizacion_'.$row->folio.'.pdf',
                    'pdf_path' => $row->pdf_path,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_cotizacion_archivos');
    }
};
