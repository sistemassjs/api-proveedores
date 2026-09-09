<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\TipoEmpresa;
use Illuminate\Support\Facades\DB;

class ProveedoresSPSeeder extends Seeder
{
    /**
     * Seeder para agregar proveedores adicionales de tipo SP (Solicitudes de Pago)
     * Configurado para Los Mochis, Sinaloa, México
     */
    public function run(): void
    {
        DB::transaction(function () {
            $default_foto_url = '/uploads/default.png';

            // === PROVEEDOR SP 4: Materiales de Construcción Los Mochis ===
            $user4 = User::factory()->proveedor()->create([
                'email' => 'proveedor@materialesconstruccion.mx',
                'name' => 'Materiales Los Mochis',
                'foto_perfil_url' => $default_foto_url,
            ]);
            $tipoComercial = TipoEmpresa::where('clave', 'comercial')->first();

            $proveedor4 = Proveedor::factory()->create([
                'nombre_comercial' => 'Materiales de Construcción Los Mochis',
                'razon_social' => 'Materiales de Construcción Los Mochis S.A. de C.V.',
                'rfc' => 'MCLM850215ABC',
                'email' => 'ventas@materialesconstruccion.mx',
                'telefono' => '6682345678',
                'logo' => 'uploads/logo_materiales_los_mochis.png',
                'pagina_web' => 'https://www.materialesconstruccionlosmochis.mx',
                'tipos_empresa_id' => $tipoComercial->id,
                'descripcion_giro_empresa' => 'Venta de materiales para construcción y acabados',
                'direccion_empresa' => 'Av. Gabriel Leyva 450, Col. Centro, Los Mochis, Sinaloa',
                'estado' => 'Sinaloa',
                'municipio' => 'Ahome',
                'codigo_postal' => '81200',
                'nombre_propietario' => 'Miguel Ángel Sandoval',
                'nombre_de_quien_registra' => 'Carmen Leticia Ruiz',
                'contacto_nombre' => 'Jesús Alberto Morales',
                'contacto_cargo' => 'Gerente de Ventas',
                'contacto_telefono' => '6682345679',
                'contacto_correo' => 'jesus.morales@materialesconstruccion.mx',
                'principal' => true,
                'calificacion' => round(mt_rand(35, 50) / 10, 2),
                'is_proveedor_sp' => true,
                'is_proveedor_catalogo' => false,
                'perfil_empresa_completo' => true,
            ]);

            $user4->proveedores()->attach($proveedor4->id, [
                'tipo_relacion' => 'PRINCIPAL',
                'activo' => true,
                'fecha_asignacion' => now(),
                'observaciones' => 'Usuario principal del proveedor - Los Mochis',
            ]);

            // === PROVEEDOR SP 5: Construcciones y Servicios Sinaloa ===
            $user5 = User::factory()->proveedor()->create([
                'email' => 'proveedor@construccionessinaloa.com',
                'name' => 'Construcciones Sinaloa',
                'foto_perfil_url' => $default_foto_url,
            ]);
            $tipoObraCivil = TipoEmpresa::where('clave', 'obra_civil')->first();

            $proveedor5 = Proveedor::factory()->create([
                'nombre_comercial' => 'Construcciones y Servicios Sinaloa',
                'razon_social' => 'Construcciones y Servicios Sinaloa S.A. de C.V.',
                'rfc' => 'CSS920310XYZ',
                'email' => 'contacto@construccionessinaloa.com',
                'telefono' => '6673456789',
                'logo' => 'uploads/logo_construcciones_sinaloa.png',
                'pagina_web' => 'https://www.construccionessinaloa.com',
                'tipos_empresa_id' => $tipoObraCivil->id,
                'descripcion_giro_empresa' => 'Construcción de obra civil, pavimentación y servicios especializados',
                'direccion_empresa' => 'Carretera Los Mochis-Topolobampo Km 8.5, Los Mochis, Sinaloa',
                'estado' => 'Sinaloa',
                'municipio' => 'Ahome',
                'codigo_postal' => '81223',
                'nombre_propietario' => 'Rosa María Velázquez',
                'nombre_de_quien_registra' => 'Francisco Javier Montoya',
                'contacto_nombre' => 'Alejandro Castro López',
                'contacto_cargo' => 'Coordinador de Proyectos',
                'contacto_telefono' => '6673456790',
                'contacto_correo' => 'alejandro.castro@construccionessinaloa.com',
                'principal' => true,
                'calificacion' => round(mt_rand(38, 50) / 10, 2),
                'is_proveedor_sp' => true,
                'is_proveedor_catalogo' => false,
                'perfil_empresa_completo' => true,
            ]);

            $user5->proveedores()->attach($proveedor5->id, [
                'tipo_relacion' => 'PRINCIPAL',
                'activo' => true,
                'fecha_asignacion' => now(),
                'observaciones' => 'Usuario principal del proveedor - Servicios especializados',
            ]);

            // === PROVEEDOR SP 6: Ferretería y Plomería del Pacífico ===
            $user6 = User::factory()->proveedor()->create([
                'email' => 'proveedor@ferreteriadelpacifico.mx',
                'name' => 'Ferretería del Pacífico',
                'foto_perfil_url' => $default_foto_url,
            ]);
            $tipoIndustrial = TipoEmpresa::where('clave', 'industrial')->first();

            $proveedor6 = Proveedor::factory()->create([
                'nombre_comercial' => 'Ferretería y Plomería del Pacífico',
                'razon_social' => 'Ferretería y Plomería del Pacífico S. de R.L. de C.V.',
                'rfc' => 'FPP880525DEF',
                'email' => 'ventas@ferreteriadelpacifico.mx',
                'telefono' => '6684567890',
                'logo' => 'uploads/logo_ferreteria_pacifico.png',
                'pagina_web' => 'https://www.ferreteriadelpacifico.mx',
                'tipos_empresa_id' => $tipoIndustrial->id,
                'descripcion_giro_empresa' => 'Venta de herramientas, plomería y materiales industriales',
                'direccion_empresa' => 'Blvd. Centenario 1250, Col. Scally, Los Mochis, Sinaloa',
                'estado' => 'Sinaloa',
                'municipio' => 'Ahome',
                'codigo_postal' => '81259',
                'nombre_propietario' => 'Eduardo Ramírez Salinas',
                'nombre_de_quien_registra' => 'Patricia Ochoa Medina',
                'contacto_nombre' => 'Luis Fernando Cota',
                'contacto_cargo' => 'Jefe de Almacén',
                'contacto_telefono' => '6684567891',
                'contacto_correo' => 'luis.cota@ferreteriadelpacifico.mx',
                'principal' => true,
                'calificacion' => round(mt_rand(40, 50) / 10, 2),
                'is_proveedor_sp' => true,
                'is_proveedor_catalogo' => false,
                'perfil_empresa_completo' => true,
            ]);

            $user6->proveedores()->attach($proveedor6->id, [
                'tipo_relacion' => 'PRINCIPAL',
                'activo' => true,
                'fecha_asignacion' => now(),
                'observaciones' => 'Usuario principal del proveedor - Herramientas y plomería',
            ]);

            // === PROVEEDOR SP 7: Transportes y Maquinaria Noroeste ===
            $user7 = User::factory()->proveedor()->create([
                'email' => 'proveedor@transportesnoroeste.com',
                'name' => 'Transportes Noroeste',
                'foto_perfil_url' => $default_foto_url,
            ]);

            $proveedor7 = Proveedor::factory()->create([
                'nombre_comercial' => 'Transportes y Maquinaria Noroeste',
                'razon_social' => 'Transportes y Maquinaria Noroeste S.A. de C.V.',
                'rfc' => 'TMN950815GHI',
                'email' => 'servicios@transportesnoroeste.com',
                'telefono' => '6685678901',
                'logo' => 'uploads/logo_transportes_noroeste.png',
                'pagina_web' => 'https://www.transportesnoroeste.com',
                'tipos_empresa_id' => $tipoIndustrial->id,
                'descripcion_giro_empresa' => 'Renta de maquinaria pesada y servicios de transporte especializado',
                'direccion_empresa' => 'Periférico Norte 2100, Parque Industrial, Los Mochis, Sinaloa',
                'estado' => 'Sinaloa',
                'municipio' => 'Ahome',
                'codigo_postal' => '81349',
                'nombre_propietario' => 'José Manuel Ibarra',
                'nombre_de_quien_registra' => 'Claudia Esperanza Félix',
                'contacto_nombre' => 'Ricardo Armando Zazueta',
                'contacto_cargo' => 'Supervisor de Operaciones',
                'contacto_telefono' => '6685678902',
                'contacto_correo' => 'ricardo.zazueta@transportesnoroeste.com',
                'principal' => true,
                'calificacion' => round(mt_rand(42, 50) / 10, 2),
                'is_proveedor_sp' => true,
                'is_proveedor_catalogo' => false,
                'perfil_empresa_completo' => true,
            ]);

            $user7->proveedores()->attach($proveedor7->id, [
                'tipo_relacion' => 'PRINCIPAL',
                'activo' => true,
                'fecha_asignacion' => now(),
                'observaciones' => 'Usuario principal del proveedor - Maquinaria y transporte',
            ]);

            echo "✅ Seeder ProveedoresSPSeeder ejecutado correctamente.\n";
            echo "📍 Se agregaron 4 proveedores SP específicos para Los Mochis, Sinaloa.\n";
        });
    }
}