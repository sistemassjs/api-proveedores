<?php

namespace App\Services\Proveedor;

use App\Enums\EstadoCuentaBancaria;
use App\Models\Proveedor;

/**
 * Reglas de completitud del perfil de empresa (Mi Empresa).
 *
 * - perfil_empresa_completado (bandera BD + banner): generales + fiscales.
 * - puede_generar_sp: además exige constancia fiscal; cuenta bancaria opcional.
 */
class ProveedorPerfilCompletadoService
{
    /**
     * @return array{
     *     generales: bool,
     *     fiscales: bool,
     *     bancarios: bool,
     *     tiene_constancia_fiscal: bool,
     *     perfil_empresa_completado: bool
     * }
     */
    public function evaluar(Proveedor $proveedor): array
    {
        $proveedor->loadMissing(['cuentasBancarias']);

        $generales = $this->validarInformacionGeneral($proveedor);
        $fiscales = $this->validarDatosFiscales($proveedor);
        $bancarios = $proveedor->cuentasBancarias
            ->where('estatus', EstadoCuentaBancaria::ACTIVA)
            ->count() > 0;
        $tieneConstanciaFiscal = ! empty($proveedor->constancia_fiscal);

        return [
            'generales' => $generales,
            'fiscales' => $fiscales,
            'bancarios' => $bancarios,
            'tiene_constancia_fiscal' => $tieneConstanciaFiscal,
            'perfil_empresa_completado' => $generales && $fiscales,
        ];
    }

    /**
     * Persiste proveedores.perfil_empresa_completo si cambió (generales + fiscales).
     */
    public function sincronizarBandera(Proveedor $proveedor): bool
    {
        $evaluacion = $this->evaluar($proveedor);
        $completado = $evaluacion['perfil_empresa_completado'];

        if ((bool) $proveedor->perfil_empresa_completo !== $completado) {
            $proveedor->update(['perfil_empresa_completo' => $completado]);
        }

        return $completado;
    }

    /**
     * @return array{
     *     puede_generar_sp: bool,
     *     detalle: array{
     *         perfil_empresa_completo: bool,
     *         tiene_cuenta_bancaria: bool,
     *         tiene_constancia_fiscal: bool,
     *         tiene_logo: bool,
     *         tiene_informacion_general_y_datos_fiscales: bool,
     *         datos_faltantes: list<string>
     *     }
     * }
     */
    public function evaluarPuedeGenerarSP(Proveedor $proveedor): array
    {
        if (! $proveedor->is_proveedor_sp) {
            return [
                'puede_generar_sp' => false,
                'detalle' => [
                    'perfil_empresa_completo' => false,
                    'tiene_cuenta_bancaria' => false,
                    'tiene_constancia_fiscal' => false,
                    'tiene_logo' => false,
                    'tiene_informacion_general_y_datos_fiscales' => false,
                    'datos_faltantes' => ['La empresa no está habilitada para generar Solicitudes de Pago'],
                ],
            ];
        }

        $evaluacion = $this->evaluar($proveedor);
        $generales = $evaluacion['generales'];
        $fiscales = $evaluacion['fiscales'];
        $tieneInformacionGeneralYDatosFiscales = $generales && $fiscales;
        $tieneConstanciaFiscal = $evaluacion['tiene_constancia_fiscal'];
        $tieneCuentaBancaria = $evaluacion['bancarios'];

        $datosFaltantes = [];
        if (! $generales) {
            $datosFaltantes[] = 'Información general de la empresa';
        }
        if (! $fiscales) {
            $datosFaltantes[] = 'Datos fiscales';
        }
        if (! $tieneConstanciaFiscal) {
            $datosFaltantes[] = 'Constancia de situación fiscal en GestionPlus';
        }

        $puedeGenerarSp = $tieneInformacionGeneralYDatosFiscales && $tieneConstanciaFiscal;

        return [
            'puede_generar_sp' => $puedeGenerarSp,
            'detalle' => [
                'perfil_empresa_completado' => $evaluacion['perfil_empresa_completado'],
                'tiene_cuenta_bancaria' => $tieneCuentaBancaria,
                'tiene_constancia_fiscal' => $tieneConstanciaFiscal,
                'tiene_logo' => true,
                'tiene_informacion_general_y_datos_fiscales' => $tieneInformacionGeneralYDatosFiscales,
                'datos_faltantes' => $datosFaltantes,
            ],
        ];
    }

    public function validarInformacionGeneral(Proveedor $proveedor): bool
    {
        foreach (['nombre_comercial', 'email'] as $campo) {
            if (empty($proveedor->$campo)) {
                return false;
            }
        }

        return true;
    }

    public function validarDatosFiscales(Proveedor $proveedor): bool
    {
        foreach (['razon_social', 'rfc'] as $campo) {
            if (empty($proveedor->$campo)) {
                return false;
            }
        }

        return true;
    }
}
