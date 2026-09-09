<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\TipoEmpresa;
use Illuminate\Support\Facades\DB;

class ProveedorSeeder extends Seeder
{
    public function run()
    {

        DB::transaction(function () {
            $default_foto_url = '/uploads/default.png';

            // === PROVEEDOR 1: Fierro y Lámina ===
            $user1 = User::factory()->proveedor()->create([
                'email' => 'proveedor@fierroylaminadigital.com',
                'name' => 'Fierro y Lamina',
                'foto_perfil_url' => $default_foto_url,
            ]);
            $tipo1 = TipoEmpresa::where('clave', 'comercial')->first();

            $proveedor1 = Proveedor::factory()->create([
                'nombre_comercial' => 'Fierro y Lámina',
                'razon_social' => 'Fierro y Lámina S.A. de C.V.',
                'rfc' => 'FILM750101XYZ',
                'email' => 'ventas@fierroylaminadigital.mx',
                'telefono' => '6671234567',
                'logo' => 'uploads/logo_fierro_y_lamina.png',
                'pagina_web' => 'https://www.fierroylaminadigital.mx/',
                'tipos_empresa_id' => $tipo1->id,
                'descripcion_giro_empresa' => 'Distribuidor de materiales de construcción',
                'direccion_empresa' => 'Blvd. Pedro Infante 123, Culiacán, Sinaloa',
                'estado' => 'Sinaloa',
                'municipio' => 'Culiacán',
                'codigo_postal' => '80000',
                'nombre_propietario' => 'Juan Fierro',
                'nombre_de_quien_registra' => 'Ana López',
                'contacto_nombre' => 'Carlos Ramírez',
                'contacto_cargo' => 'Ventas',
                'contacto_telefono' => '6678901234',
                'contacto_correo' => 'carlos.ramirez@fierroylaminadigital.mx',
                'principal' => true,
                'calificacion' => round(mt_rand(30, 50) / 10, 2),
                'is_proveedor_sp' => true,
                'is_proveedor_catalogo' => false,
                'perfil_empresa_completo' => true,
            ]);
            // $user1->proveedores()->attach($proveedor1->id, ['is_main' => true]);
            $user1->proveedores()->attach($proveedor1->id, [
                'tipo_relacion' => 'PRINCIPAL',
                'activo' => true,
                'fecha_asignacion' => now(),
                'observaciones' => 'Usuario principal del proveedor',
            ]);
            // === PROVEEDOR 2: Truper ===
            $user2 = User::factory()->proveedor()->create([
                'email' => 'proveedor@truper.com',
                'name' => 'Truper',
                'foto_perfil_url' => $default_foto_url,
            ]);
            $tipo2 = TipoEmpresa::where('clave', 'industrial')->first();

            $proveedor2 = Proveedor::factory()->create([
                'nombre_comercial' => 'Truper',
                'razon_social' => 'Truper S.A. de C.V.',
                'rfc' => 'TRU850101XYZ',
                'email' => 'contacto@truper.com',
                'telefono' => '8000187873',
                'logo' => 'uploads/logo_truper.png',
                'pagina_web' => 'https://www.truper.com/',
                'tipos_empresa_id' => $tipo2->id,
                'descripcion_giro_empresa' => 'Fabricación y distribución de herramientas',
                'direccion_empresa' => 'Carretera México-Laredo Km 155, Jilotepec, Estado de México',
                'estado' => 'Estado de México',
                'municipio' => 'Jilotepec',
                'codigo_postal' => '54240',
                'nombre_propietario' => 'Roberto Trujillo',
                'nombre_de_quien_registra' => 'Laura Méndez',
                'contacto_nombre' => 'Fernando Pérez',
                'contacto_cargo' => 'Gerente Comercial',
                'contacto_telefono' => '5556781234',
                'contacto_correo' => 'fernando.perez@truper.com',
                'principal' => true,
                'calificacion' => round(mt_rand(30, 50) / 10, 2),
                'is_proveedor_sp' => false,
                'is_proveedor_catalogo' => true,
                'perfil_empresa_completo' => true,
            ]);
            // $user2->proveedores()->attach($proveedor2->id, ['is_main' => true]);
            $user2->proveedores()->attach($proveedor2->id, [
                'tipo_relacion' => 'PRINCIPAL',
                'activo' => true,
                'fecha_asignacion' => now(),
                'observaciones' => 'Usuario principal del proveedor',
            ]);
            // === PROVEEDOR 3: Ejemplo ficticio ===
            $user3 = User::factory()->proveedor()->create([
                'email' => 'proveedor@elgrangero.com',
                'name' => 'El Gran Gero',
                'foto_perfil_url' => $default_foto_url,
            ]);
            $tipo3 = TipoEmpresa::where('clave', 'obra_civil')->first();

            $proveedor3 = Proveedor::factory()->create([
                'nombre_comercial' => 'Granjas ElGranGero',
                'razon_social' => 'Granjas ElGranGero S.A. de C.V.',
                'rfc' => 'COEX900101XYZ',
                'email' => 'contacto@elgrangero.com',
                'telefono' => '4491234567',
                'logo' => 'uploads/logo_el_gran_gero.png',
                'pagina_web' => 'https://www.elgrangero.com',
                'tipos_empresa_id' => $tipo3->id,
                'descripcion_giro_empresa' => 'Obra civil y pavimentación',
                'direccion_empresa' => 'Av. Central 456, Aguascalientes, Ags.',
                'estado' => 'Aguascalientes',
                'municipio' => 'Aguascalientes',
                'codigo_postal' => '20000',
                'nombre_propietario' => 'Patricia Torres',
                'nombre_de_quien_registra' => 'Luis Gómez',
                'contacto_nombre' => 'Verónica Salas',
                'contacto_cargo' => 'Asistente Administrativo',
                'contacto_telefono' => '4492345678',
                'contacto_correo' => 'veronica@elgrangero.com',
                'principal' => true,
                'calificacion' => round(mt_rand(30, 50) / 10, 2),
                'is_proveedor_sp' => true,
                'is_proveedor_catalogo' => false,
                'perfil_empresa_completo' => true,
            ]);
            // $user3->proveedores()->attach($proveedor3->id, ['is_main' => true]);
            $user3->proveedores()->attach($proveedor3->id, [
                'tipo_relacion' => 'PRINCIPAL',
                'activo' => true,
                'fecha_asignacion' => now(),
                'observaciones' => 'Usuario principal del proveedor',
            ]);
        });
    }
}
