<?php

use App\Enums\UserRoleEnumerate;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ProveedorSolicitudPagoController;
use App\Http\Controllers\ProveedorCotizacionController;
use App\Http\Controllers\ProveedorMarcaController;
// use App\Http\Controllers\ProveedorPedidoController;
use App\Http\Controllers\ProveedorUsuarioController;
use App\Http\Controllers\SucursalProductoController;
use App\Http\Controllers\ProveedorSucursalController;
use App\Http\Controllers\ProveedorProductoController;
use App\Http\Controllers\ProveedorProductoDocumentoController;
use App\Http\Controllers\ProveedorCategoriaController;
use App\Http\Controllers\ProveedorDashboardController;
use App\Http\Controllers\ProveedorUnidadMedidaController;
use App\Http\Controllers\ProveedorCuentaBancariaController;
use App\Http\Controllers\EmpresaConstruccController;
// use App\Http\Controllers\OrdenCompraController;
// use App\Http\Controllers\OrdenCompraRegistroController;
// use App\Http\Controllers\ProveedorOrdenCompraDashboardController;
use App\Http\Controllers\OrdenCompraSolicitudPagoController;
use App\Http\Controllers\ProveedorOrdenCompraController;
use App\Http\Controllers\ProveedorPresupuestoController;
use App\Http\Controllers\ProveedorPresupuestoCarteraClientesController;
use App\Http\Controllers\ProveedorPresupuestoCatalogoConceptosController;
use App\Http\Controllers\ProveedorPresupuestoPlantillaController;
use App\Http\Controllers\ProveedorPresupuestoPlantillaAnexoController;
use App\Http\Controllers\ProveedorPresupuestoPlantillaAnexoPdfController;
use App\Http\Controllers\ProveedorPresupuestoAnexoController;
use App\Http\Controllers\ProveedorPresupuestoAnexoPdfController;
use App\Http\Controllers\ProveedorPresupuestoConfigController;
use App\Http\Controllers\ProveedorPerfilPublicoController;

/**
 * GESTIÓN DE PROVEEDORES
 * Acceso temporal completo a GERENTE / SUPERVISOR / VENTAS / AUXILIAR.
 * La matriz fina (lectura vs escritura por módulo) se pulirá después.
 * @see docs/context/platform-users-roles.md
 * @see config/proveedor_gestion_mvp.php → roles_acceso_rutas_proveedor
 */
$rolesProveedor = implode(',', config('proveedor_gestion_mvp.roles_acceso_rutas_proveedor', [
    UserRoleEnumerate::GERENTE->value,
    UserRoleEnumerate::SUPERVISOR->value,
    UserRoleEnumerate::VENTAS->value,
    UserRoleEnumerate::AUXILIAR->value,
]));

Route::prefix('proveedores')
    ->middleware(['auth:sanctum', 'role:' . $rolesProveedor])
    ->group(function () {
        Route::prefix('api-proveedor/presupuestos')->group(function () {
            Route::get('/next-folio', [ProveedorPresupuestoController::class, 'nextFolio']);
        });


        /**
         * CRUD BASICO
         */
        Route::post('/', [ProveedorController::class, 'store']);
        Route::get('{proveedor}', [ProveedorController::class, 'show'])->middleware(['api.access']);
        Route::patch('{proveedor}', [ProveedorController::class, 'update'])->middleware(['api.access']);
        Route::delete('{proveedor}', [ProveedorController::class, 'destroy'])->middleware(['api.access']);
        Route::post('{proveedor}/logo', [ProveedorController::class, 'updateLogo'])->middleware(['api.access']);
        Route::post('/verificar-rfc', [ProveedorController::class, 'verificarRfcExistente']);
        Route::post('{proveedor}/verificar-rfc-excluyendo-proveedor', [ProveedorController::class, 'verificarRfcExistenteExcluyendoProveedor']);
        Route::post('{proveedor}/verificar-razon-social-excluyendo-proveedor', [ProveedorController::class, 'verificarRazonSocialExistenteExcluyendoProveedor']);

        // Consultas especiales
        Route::get('user/{id}', [ProveedorController::class, 'getProveedorByUserId']);

        /**
         * CUENTAS BANCARIAS
         */
        Route::prefix('{proveedor}/cuentas-bancarias')->middleware(['proveedor.access'])->group(function () {
            Route::get('/', [ProveedorCuentaBancariaController::class, 'index'])->middleware(['api.access']);
            Route::get('/preferida', [ProveedorCuentaBancariaController::class, 'getPreferida'])->middleware(['api.access']);
            Route::post('/preferida', [ProveedorCuentaBancariaController::class, 'setPreferida'])->middleware(['api.access']);
            Route::post('/', [ProveedorCuentaBancariaController::class, 'store'])->middleware(['api.access']);
            Route::middleware(['api.access', 'proveedor.cuenta'])->group(function () {
                Route::get('{cuenta}', [ProveedorCuentaBancariaController::class, 'show']);
                Route::patch('{cuenta}', [ProveedorCuentaBancariaController::class, 'update']);
                Route::delete('{cuenta}', [ProveedorCuentaBancariaController::class, 'destroy']);
            });
        });

        Route::prefix('{proveedor}/constancia-fiscal')->middleware(['proveedor.access'])->group(function () {
            Route::post('/', [ProveedorController::class, 'updateConstanciaFiscal']);
            Route::get('/preview', [ProveedorController::class, 'previewConstanciaFiscal']);
            Route::get('/download', [ProveedorController::class, 'downloadConstanciaFiscal']);
        });

        /**
         * PERFIL PÚBLICO DE EMPRESA (plataforma — compartir información)
         */
        Route::prefix('{proveedor}/perfil-publico')->middleware(['proveedor.access'])->group(function () {
            Route::get('/themes', [ProveedorPerfilPublicoController::class, 'themes']);
            Route::get('/', [ProveedorPerfilPublicoController::class, 'show']);
            Route::put('/', [ProveedorPerfilPublicoController::class, 'update']);
            Route::post('/publicar', [ProveedorPerfilPublicoController::class, 'publicar']);
            Route::post('/despublicar', [ProveedorPerfilPublicoController::class, 'despublicar']);
        });

        /**
         * USUARIOS DEL PROVEEDOR
         */
        Route::prefix('{proveedor}/users')->middleware(['proveedor.access'])->group(function () {
            Route::get('/', [ProveedorUsuarioController::class, 'index']);
            Route::post('/', [ProveedorUsuarioController::class, 'store']);
            Route::middleware(['api.access', 'proveedor.user'])->group(function () {
                Route::get('{user}', [ProveedorUsuarioController::class, 'show']);
                // POST también: multipart (logo) no siempre llega bien con PATCH en PHP
                Route::match(['patch', 'post'], '{user}', [ProveedorUsuarioController::class, 'update']);
                Route::delete('{user}', [ProveedorUsuarioController::class, 'destroy']);
                Route::post('{user}/logo', [ProveedorUsuarioController::class, 'updateLogo']);
                Route::patch('{user}/relacion', [ProveedorUsuarioController::class, 'updateRelacion']);
                Route::patch('{user}/estado', [ProveedorUsuarioController::class, 'cambiarEstado']);
            });
        });

        /**
         *
         * PRODUCTOS DEL PROVEEDOR
         */
        Route::prefix('{proveedor}/productos')->middleware(['proveedor.access'])->group(function () {
            Route::get('/', [ProveedorProductoController::class, 'index']);
            Route::post('/', [ProveedorProductoController::class, 'store']);
            Route::post('/bulk', [ProveedorProductoController::class, 'bulkStore']);
            Route::middleware(['proveedor.producto'])->group(function () {
                Route::get('{producto}', [ProveedorProductoController::class, 'show']);
                Route::patch('{producto}', [ProveedorProductoController::class, 'update']);
                Route::delete('{producto}', [ProveedorProductoController::class, 'destroy']);
                Route::post('{producto}/logo', [ProveedorProductoController::class, 'updateLogo']);

                Route::get('{producto}/documentos', [ProveedorProductoDocumentoController::class, 'index']);
                Route::post('{producto}/documentos', [ProveedorProductoDocumentoController::class, 'store']);
                Route::delete('{producto}/documentos/{documento}', [ProveedorProductoDocumentoController::class, 'destroy']);
            });
        });

        /**
         * CATEGORÍAS DEL PROVEEDOR
         */
        Route::prefix('{proveedor}/categorias')->middleware(['proveedor.access'])->group(function () {
            Route::get('/', [ProveedorCategoriaController::class, 'index']);
            Route::post('/', [ProveedorCategoriaController::class, 'store']);
            Route::get('/all', [ProveedorCategoriaController::class, 'all']);
            Route::get('/all/count-products', [ProveedorCategoriaController::class, 'categoriasConSubcatCountProductos']);

            Route::middleware(['proveedor.categoria'])->group(function () {
                Route::get('{categoria}', [ProveedorCategoriaController::class, 'show']);
                Route::patch('{categoria}', [ProveedorCategoriaController::class, 'update']);
                Route::delete('{categoria}', [ProveedorCategoriaController::class, 'destroy']);
                Route::post('{categoria}/logo', [ProveedorCategoriaController::class, 'updateLogo']);
                Route::get('{categoria}/subcategorias', [ProveedorCategoriaController::class, 'index_sub_categorias']);
            });
        });

        /**
         * MARCAS DEL PROVEEDOR
         */
        Route::prefix('{proveedor}/marcas')->middleware(['proveedor.access'])->group(function () {
            Route::get('/', [ProveedorMarcaController::class, 'index']);
            Route::post('/', [ProveedorMarcaController::class, 'store']);
            Route::get('/all', [ProveedorMarcaController::class, 'all']);
            Route::middleware(['proveedor.marca'])->group(function () {
                Route::get('{marca}', [ProveedorMarcaController::class, 'show']);
                Route::patch('{marca}', [ProveedorMarcaController::class, 'update']);
                Route::delete('{marca}', [ProveedorMarcaController::class, 'destroy']);
                Route::post('{marca}/logo', [ProveedorMarcaController::class, 'updateLogo']);
                // Route::get('{marca}/lineas', [ProveedorMarcaController::class, 'index_lineas_por_marca']);
            });
        });

        /**
         * UNIDA MEDIDA DEL PROVEEDOR
         */
        Route::prefix('{proveedor}/unidades')->middleware(['proveedor.access'])->group(function () {
            Route::get('/', [ProveedorUnidadMedidaController::class, 'index']);
            Route::post('/', [ProveedorUnidadMedidaController::class, 'store']);
            Route::get('/all', [ProveedorUnidadMedidaController::class, 'all']);
            Route::middleware(['proveedor.unidad'])->group(function () {
                Route::get('{unidad}', [ProveedorUnidadMedidaController::class, 'show']);
                Route::patch('{unidad}', [ProveedorUnidadMedidaController::class, 'update']);
                Route::delete('{unidad}', [ProveedorUnidadMedidaController::class, 'destroy']);
            });
        });


        /**
         * SUCURSALES DEL PROVEEDOR
         */
        Route::prefix('{proveedor}/sucursales')->middleware(['proveedor.access'])->group(function () {
            Route::get('/', [ProveedorSucursalController::class, 'index']);
            Route::post('/', [ProveedorSucursalController::class, 'store']);
            Route::middleware(['proveedor.sucursal'])->group(function () {
                Route::get('{sucursal}', [ProveedorSucursalController::class, 'show']);
                Route::delete('{sucursal}', [ProveedorSucursalController::class, 'destroy']);
                Route::patch('{sucursal}', [ProveedorSucursalController::class, 'update']);

                /**
                 * PRODUCTOS POR SUCURSAL
                 */
                Route::prefix('productos')->group(function () {
                    Route::get('/', [SucursalProductoController::class, 'index']);
                    Route::post('asignar', [SucursalProductoController::class, 'asignarProductos']);
                    Route::delete('desasignar', [SucursalProductoController::class, 'desasignarProductos']);
                    Route::patch('{producto}', [SucursalProductoController::class, 'updateStock']);
                });
            });
        });

        Route::get('{proveedor}/puede-generar-sp', [ProveedorController::class, 'puedeGenerarSP']);


        /**
         * CSV IMPORT ROUTES
         */
        Route::prefix('{proveedor}/csv-import')->middleware(['proveedor.access'])->group(function () {
            Route::post('/upload', [CsvImportController::class, 'upload']);
            // Route::post('/validate-producto', [CsvImportController::class, 'validateProducto']);
            Route::post('/confirm', [CsvImportController::class, 'confirm']);
            Route::get('/status/{auditId}', [CsvImportController::class, 'getImportStatus']);
            Route::get('/results/{auditId}', [CsvImportController::class, 'getImportResults']);
            Route::get('/results/{auditId}/export', [CsvImportController::class, 'export']);
        });

        /**
         * DASHBOARD PROVEEDOR
         * Métricas agregadas del catálogo / cotizaciones.
         * Contadores SP: GET {proveedor}/solicitudes-pago/dashboard/metricas (ProveedorSolicitudPagoController).
         * Presupuestos: consolidar en este grupo cuando se migre el front desde métricas sueltas.
         */
        Route::prefix('{proveedor}/dashboard')
            ->middleware(['proveedor.access'])
            ->group(function () {
                Route::get('/stats', [ProveedorDashboardController::class, 'getStats']);
                Route::get('/cotizaciones', [ProveedorDashboardController::class, 'cotizacionesDashboard']);
            });

        /**
         * COTIZACIONES DEL PROVEEDOR
         */
        Route::prefix('{proveedor}/cotizaciones')
            ->middleware(['proveedor.access'])
            ->group(function () {
                Route::get('/', [ProveedorCotizacionController::class, 'index']);
                Route::get('/all', [ProveedorCotizacionController::class, 'uindex']);
                Route::post('/', [ProveedorCotizacionController::class, 'store']);
                // Rutas con más segmentos antes de /{cotizacion} (convención Laravel)
                Route::get('/{cotizacion}/descargar-pdf', [ProveedorCotizacionController::class, 'descargarPdf']);
                Route::get('/{cotizacion}', [ProveedorCotizacionController::class, 'show']);
                Route::put('/{cotizacion}', [ProveedorCotizacionController::class, 'update']);
                Route::delete('/{cotizacion}', [ProveedorCotizacionController::class, 'destroy']);
            });

        /**
         * PRESUPUESTOS DEL PROVEEDOR
         * Orden: literales (proveedores-registrados, next-folio, generar-pdf) antes de /{presupuesto}.
         */
        Route::prefix('{proveedor}/presupuestos')
            ->middleware(['proveedor.access'])
            ->group(function () {

                Route::get('/proveedores-registrados', [ProveedorPresupuestoController::class, 'proveedoresRegistrados']);

                Route::prefix('cartera-clientes')->group(function () {
                    Route::get('/', [ProveedorPresupuestoCarteraClientesController::class, 'index']);
                    Route::post('/', [ProveedorPresupuestoCarteraClientesController::class, 'store']);
                    Route::get('/{carteraCliente}', [ProveedorPresupuestoCarteraClientesController::class, 'show']);
                    Route::put('/{carteraCliente}', [ProveedorPresupuestoCarteraClientesController::class, 'update']);
                    Route::patch('/{carteraCliente}', [ProveedorPresupuestoCarteraClientesController::class, 'update']);
                    Route::delete('/{carteraCliente}', [ProveedorPresupuestoCarteraClientesController::class, 'destroy']);
                });

                Route::prefix('presupuesto-catalogo-conceptos')->group(function () {
                    Route::get('/', [ProveedorPresupuestoCatalogoConceptosController::class, 'index']);
                    Route::post('/', [ProveedorPresupuestoCatalogoConceptosController::class, 'store']);
                    Route::get('/sugerencias', [ProveedorPresupuestoCatalogoConceptosController::class, 'sugerencias']);
                    Route::get('/{presupuestoCatalogoConcepto}', [ProveedorPresupuestoCatalogoConceptosController::class, 'show']);
                    Route::put('/{presupuestoCatalogoConcepto}', [ProveedorPresupuestoCatalogoConceptosController::class, 'update']);
                    Route::patch('/{presupuestoCatalogoConcepto}', [ProveedorPresupuestoCatalogoConceptosController::class, 'update']);
                    Route::delete('/{presupuestoCatalogoConcepto}', [ProveedorPresupuestoCatalogoConceptosController::class, 'destroy']);
                });

                Route::prefix('plantillas')->group(function () {
                    Route::get('/', [ProveedorPresupuestoPlantillaController::class, 'index']);
                    Route::post('/', [ProveedorPresupuestoPlantillaController::class, 'store']);
                    Route::post('/desde-presupuesto/{presupuesto}', [ProveedorPresupuestoPlantillaController::class, 'desdePresupuesto']);

                    Route::prefix('{plantilla}/anexos')->group(function () {
                        Route::get('/', [ProveedorPresupuestoPlantillaAnexoController::class, 'index']);
                        Route::post('/bulk', [ProveedorPresupuestoPlantillaAnexoController::class, 'storeBulk']);
                        Route::post('/', [ProveedorPresupuestoPlantillaAnexoController::class, 'store']);
                        Route::get('/{anexo}', [ProveedorPresupuestoPlantillaAnexoController::class, 'show']);
                        Route::post('/{anexo}', [ProveedorPresupuestoPlantillaAnexoController::class, 'update']);
                        Route::patch('/{anexo}', [ProveedorPresupuestoPlantillaAnexoController::class, 'update']);
                        Route::delete('/{anexo}', [ProveedorPresupuestoPlantillaAnexoController::class, 'destroy']);
                    });

                    Route::prefix('{plantilla}/anexos-pdf')->group(function () {
                        Route::get('/', [ProveedorPresupuestoPlantillaAnexoPdfController::class, 'index']);
                        Route::post('/', [ProveedorPresupuestoPlantillaAnexoPdfController::class, 'store']);
                        Route::get('/{anexo}', [ProveedorPresupuestoPlantillaAnexoPdfController::class, 'show']);
                        Route::post('/{anexo}', [ProveedorPresupuestoPlantillaAnexoPdfController::class, 'update']);
                        Route::patch('/{anexo}', [ProveedorPresupuestoPlantillaAnexoPdfController::class, 'update']);
                        Route::delete('/{anexo}', [ProveedorPresupuestoPlantillaAnexoPdfController::class, 'destroy']);
                    });

                    Route::get('/{plantilla}', [ProveedorPresupuestoPlantillaController::class, 'show']);
                    Route::put('/{plantilla}', [ProveedorPresupuestoPlantillaController::class, 'update']);
                    Route::patch('/{plantilla}', [ProveedorPresupuestoPlantillaController::class, 'update']);
                    Route::delete('/{plantilla}', [ProveedorPresupuestoPlantillaController::class, 'destroy']);
                    Route::post('/{plantilla}/aplicar', [ProveedorPresupuestoPlantillaController::class, 'aplicar']);
                    Route::post('/{plantilla}/aplicar-sobre/{presupuesto}', [ProveedorPresupuestoPlantillaController::class, 'aplicarSobre']);
                });

                Route::get('/next-folio', [ProveedorPresupuestoController::class, 'nextFolioByProveedor']);
                Route::get('/pdf-themes', [ProveedorPresupuestoController::class, 'listPdfThemes']);
                Route::get('/', [ProveedorPresupuestoController::class, 'index']);
                Route::post('/', [ProveedorPresupuestoController::class, 'store']);
                Route::post('/generar-pdf', [ProveedorPresupuestoController::class, 'generarPdfDesdeFormulario']);
                Route::prefix('{presupuesto}/anexos')->group(function () {
                    Route::get('/', [ProveedorPresupuestoAnexoController::class, 'index']);
                    Route::post('/bulk', [ProveedorPresupuestoAnexoController::class, 'storeBulk']);
                    Route::post('/', [ProveedorPresupuestoAnexoController::class, 'store']);
                    Route::get('/{anexo}', [ProveedorPresupuestoAnexoController::class, 'show']);
                    Route::post('/{anexo}', [ProveedorPresupuestoAnexoController::class, 'update']);
                    Route::patch('/{anexo}', [ProveedorPresupuestoAnexoController::class, 'update']);
                    Route::delete('/{anexo}', [ProveedorPresupuestoAnexoController::class, 'destroy']);
                });

                Route::prefix('{presupuesto}/anexos-pdf')->group(function () {
                    Route::get('/', [ProveedorPresupuestoAnexoPdfController::class, 'index']);
                    Route::post('/', [ProveedorPresupuestoAnexoPdfController::class, 'store']);
                    Route::get('/{anexoPdf}', [ProveedorPresupuestoAnexoPdfController::class, 'show']);
                    Route::post('/{anexoPdf}', [ProveedorPresupuestoAnexoPdfController::class, 'update']);
                    Route::patch('/{anexoPdf}', [ProveedorPresupuestoAnexoPdfController::class, 'update']);
                    Route::delete('/{anexoPdf}', [ProveedorPresupuestoAnexoPdfController::class, 'destroy']);
                });

                Route::patch('/{presupuesto}/pdf-theme', [ProveedorPresupuestoController::class, 'updatePdfTheme']);
                Route::get('/{presupuesto}/pdf', [ProveedorPresupuestoController::class, 'generarPdf']);
                Route::post('/{presupuesto}/duplicar', [ProveedorPresupuestoController::class, 'duplicar']);
                Route::post('/{presupuesto}/enviar', [ProveedorPresupuestoController::class, 'enviar']);
                Route::post('/{presupuesto}/enviar-correo', [ProveedorPresupuestoController::class, 'enviarCorreo']);
                Route::post('/{presupuesto}/notificar-receptor-app', [ProveedorPresupuestoController::class, 'notificarReceptorApp']);
                Route::post('/{presupuesto}/reenviar', [ProveedorPresupuestoController::class, 'reenviar']);
                Route::get('/{presupuesto}', [ProveedorPresupuestoController::class, 'show']);
                Route::put('/{presupuesto}', [ProveedorPresupuestoController::class, 'update']);
                Route::patch('/{presupuesto}', [ProveedorPresupuestoController::class, 'update']);
                Route::delete('/{presupuesto}', [ProveedorPresupuestoController::class, 'destroy']);
            });

        /**
         * CONFIGURACIÓN DE EMISOR/RECEPTOR DE PRESUPUESTOS
         */
        Route::prefix('{proveedor}/config-emisor-receptor-presupuestos')
            ->middleware(['proveedor.access'])
            ->group(function () {
                Route::get('/', [ProveedorPresupuestoConfigController::class, 'index']);
                Route::post('/', [ProveedorPresupuestoConfigController::class, 'store']);
                Route::put('/{config}', [ProveedorPresupuestoConfigController::class, 'update']);
                Route::post('/{config}', [ProveedorPresupuestoConfigController::class, 'update']);
                Route::delete('/{config}', [ProveedorPresupuestoConfigController::class, 'destroy']);
                Route::get('/{config}', [ProveedorPresupuestoConfigController::class, 'show']);
            });
        // Route::get('imports/products/template', [ProductoImportController::class, 'downloadTemplate']);



        /**
         * GESTIÓN DE SP POR PROVEEDOR
         * Rutas literales (all, historico, dashboard/metricas, sin-factura) antes de /{solicitudPago}.
         */
        Route::prefix('{proveedor}/solicitudes-pago')
            ->middleware(['proveedor.access'])
            ->group(function () {

            // Listados
            Route::get('/', [ProveedorSolicitudPagoController::class, 'index'])->middleware(['audit']);        // Paginado
            Route::get('/all', [ProveedorSolicitudPagoController::class, 'uindex'])->middleware(['audit']);    // Sin paginación
            Route::get('/historico', [ProveedorSolicitudPagoController::class, 'historico'])->middleware(['audit']); // Histórico OC y SP
            Route::get('/conteo-por-estado', [ProveedorSolicitudPagoController::class, 'conteoPorEstado'])->middleware(['audit']); // Conteo por segmento
            Route::get('/dashboard/metricas', [ProveedorSolicitudPagoController::class, 'getDashboardMetrics'])->middleware(['audit']); // Métricas dashboard

            // Empresas de construcción para búsqueda
            Route::get('/empresas-constructoras', [ProveedorSolicitudPagoController::class, 'empresasConstructoras'])->middleware(['audit']);

                Route::post('/{solicitudPago}/subir-comprobante', [ProveedorSolicitudPagoController::class, 'subirComprobantePago']);
                Route::post('/{solicitudPago}/subir-factura', [ProveedorSolicitudPagoController::class, 'uploadFacturaPdfXml']);
                Route::post('/{solicitudPago}/subir-factura-pdf', [ProveedorSolicitudPagoController::class, 'uploadFacturaPdf']);
                Route::post('/{solicitudPago}/subir-factura-xml', [ProveedorSolicitudPagoController::class, 'uploadFacturaXml']);
                Route::get('/{solicitudPago}/descargar-comprobante', [ProveedorSolicitudPagoController::class, 'descargarComprobantePago']);
                Route::get('/{solicitudPago}/descargar-factura-pdf', [ProveedorSolicitudPagoController::class, 'descargarFacturaPdf']);
                Route::get('/{solicitudPago}/descargar-factura-xml', [ProveedorSolicitudPagoController::class, 'descargarFacturaXml']);
                Route::get('/{solicitudPago}/descargar-cotizacion', [ProveedorSolicitudPagoController::class, 'descargarCotizacion']);
                Route::post('/{solicitudPago}/confirmar-pago', [ProveedorSolicitudPagoController::class, 'confirmarPagoSP']);
                Route::post('/{solicitudPago}/procesando', [ProveedorSolicitudPagoController::class, 'procesando']);

            // Operaciones sobre una solicitud específica
            Route::get('/{solicitudPago}', [ProveedorSolicitudPagoController::class, 'show'])->middleware(['audit']);       // Detalle
            Route::put('/{solicitudPago}', [ProveedorSolicitudPagoController::class, 'update'])->middleware(['audit']);     // Actualizar
            Route::delete('/{solicitudPago}', [ProveedorSolicitudPagoController::class, 'destroy'])->middleware(['audit']); // Eliminar

            // Subir comprobante
            Route::post('/{solicitudPago}/subir-comprobante', [ProveedorSolicitudPagoController::class, 'subirComprobantePago'])->middleware(['audit']);
            Route::post('/{solicitudPago}/subir-factura', [ProveedorSolicitudPagoController::class, 'uploadFacturaPdfXml'])->middleware(['audit']);
            Route::post('/{solicitudPago}/subir-factura-pdf', [ProveedorSolicitudPagoController::class, 'uploadFacturaPdf'])->middleware(['audit']);
            Route::post('/{solicitudPago}/subir-factura-xml', [ProveedorSolicitudPagoController::class, 'uploadFacturaXml'])->middleware(['audit']);

            // Descargar archivos (solo usuarios autorizados)
            Route::get('/{solicitudPago}/descargar-comprobante', [ProveedorSolicitudPagoController::class, 'descargarComprobantePago'])->middleware(['audit']);
            Route::get('/{solicitudPago}/descargar-factura-pdf', [ProveedorSolicitudPagoController::class, 'descargarFacturaPdf'])->middleware(['audit']);
            Route::get('/{solicitudPago}/descargar-factura-xml', [ProveedorSolicitudPagoController::class, 'descargarFacturaXml'])->middleware(['audit']);
            Route::get('/{solicitudPago}/descargar-cotizacion', [ProveedorSolicitudPagoController::class, 'descargarCotizacion'])->middleware(['audit']);

            // Cambiar estado
            Route::post('/{solicitudPago}/confirmar-pago', [ProveedorSolicitudPagoController::class, 'confirmarPagoSP'])->middleware(['audit']);
            Route::post('/{solicitudPago}/procesando', [ProveedorSolicitudPagoController::class, 'procesando'])->middleware(['audit']);
        });

        // Pagos SPP (parciales) del proveedor
        Route::get('{proveedor}/pagos-spp/{pago}/descargar-comprobante', [ProveedorSolicitudPagoController::class, 'descargarComprobantePagoParcial'])
            ->middleware(['proveedor.access'])
            ->name('proveedores.pagos-spp.descargar-comprobante');

        /**
         * GESTIÓN DE ÓRDENES DE COMPRA
         */
        Route::prefix('{proveedor}/ordenes-compra')
            ->middleware(['proveedor.access'])
            ->group(function () {

                // === RUTAS DE REGISTRO (desde frontend) ===
                /** ESTAS YA NO SON NECESARIAS */
                // Route::post('/registro', [OrdenCompraRegistroController::class, 'store']);
                // Route::put('/registro/upsert', [OrdenCompraRegistroController::class, 'upsert']);
                // Route::post('/registro/batch', [OrdenCompraRegistroController::class, 'storeBatch']);
                // Route::post('/registro/check-existence', [OrdenCompraRegistroController::class, 'checkExistence']);

                // === NUEVAS RUTAS: CONSULTA DESDE API CONSTRUCCIONES ===
                // Listado de OC del proveedor
                Route::get('/consultar', [ProveedorOrdenCompraController::class, 'index']);
                // Detalle de OC
                Route::get('/consultar/{ordenCompraId}', [ProveedorOrdenCompraController::class, 'show']);
                // Detalle de OC con las SP enlazadas
                Route::get('/consultar/{ordenCompraId}/solicitud-pago', [ProveedorOrdenCompraController::class, 'show']);

                /**
                 * LAS OC ESTAN ALMACENADAS EN LA API DE CONSTRUCCIONES
                 * ESTAS RUTAS SON SOLO DE CONSULTA, Y SE HACE MEDIATE API_KEY
                 * NO SE PERMITE CREAR, ACTUALIZAR O ELIMINAR DESDE AQUI
                 */
                // // === DASHBOARD Y ESTADÍSTICAS ===
                // Route::get('/dashboard', [ProveedorOrdenCompraDashboardController::class, 'dashboard']); // Dashboard completo
                // Route::get('/dashboard/estado-general', [ProveedorOrdenCompraDashboardController::class, 'estadoGeneral']); // Estado OC/SP
                // Route::get('/dashboard/actividad-reciente', [ProveedorOrdenCompraDashboardController::class, 'actividadReciente']); // Actividad
                // Route::get('/dashboard/metricas', [ProveedorOrdenCompraDashboardController::class, 'metricas']); // Métricas rendimiento
                // Route::get('/dashboard/estadisticas', [ProveedorOrdenCompraDashboardController::class, 'estadisticas']); // Legacy (compatibilidad)
                // Route::get('/alertas/sin-solicitudes', [ProveedorOrdenCompraDashboardController::class, 'ordenesSinSolicitudes']);
                // Route::get('/contadores/sp', [ProveedorOrdenCompraDashboardController::class, 'contadores']);

                // // === RUTAS PRINCIPALES DE CONSULTA ===
                // Route::get('/', [ProveedorOrdenCompraDashboardController::class, 'index']); // Dashboard con listado y filtros
                // Route::get('/disponibles/conversion', [ProveedorOrdenCompraDashboardController::class, 'getOrdenesDisponibles']);
                // Route::get('/{ordenCompra}', [ProveedorOrdenCompraDashboardController::class, 'show']); // Detalle OC
                // Route::get('/{ordenCompra}/solicitudes-pago', [ProveedorOrdenCompraDashboardController::class, 'getSolicitudesPago']); // SP enlazadas

                // // === RUTAS DIRECTAS CON CONTEXTO DE PROVEEDOR ===
                // Route::get('/id/{ordenCompra}', [ProveedorOrdenCompraDashboardController::class, 'showDirecto']); // Detalle OC por ID
                // Route::get('/id/{ordenCompra}/solicitudes-pago', [ProveedorOrdenCompraDashboardController::class, 'getSolicitudesPagoDirecto']); // SP de OC por ID
                // Route::get('/general', [ProveedorOrdenCompraDashboardController::class, 'indexGeneral']); // Listado general
            });

        /**
         * CONVERSIÓN DE ÓRDENES DE COMPRA A SOLICITUDES DE PAGO
         */
        Route::prefix('{proveedor}/ordenes-compra-sp')
            ->middleware(['proveedor.access'])
            ->group(function () {

                // === RUTAS DE CONVERSIÓN ===
                Route::post('/convert', [OrdenCompraSolicitudPagoController::class, 'store']); // Crear SP desde OC
                Route::post('/validate', [OrdenCompraSolicitudPagoController::class, 'validateConversion']); // Pre-validar conversión
                Route::get('/preview', [OrdenCompraSolicitudPagoController::class, 'getConversionPreview']); // Preview (datos pre-llenados)
                Route::delete('/unlink', [OrdenCompraSolicitudPagoController::class, 'unlinkSolicitudPago']); // Desasociar SP de OC

                // === RUTAS DE CONSULTA / MÉTRICAS ===
                Route::get('/{ordenCompra}/history', [OrdenCompraSolicitudPagoController::class, 'getConversionHistory']); // Historial de conversiones
                Route::get('/metricas', [OrdenCompraSolicitudPagoController::class, 'getMetricasConversion']); // Métricas de conversión
                Route::get('/recientes', [OrdenCompraSolicitudPagoController::class, 'getConversionesRecientes']); // Conversiones recientes
            });


        /**
         * GESTIÓN DE EMPRESAS DE CONSTRUCCIÓN
         */
        /**
         * GESTIÓN DE EMPRESAS DE CONSTRUCCIÓN
         */
        Route::prefix('{proveedor}/empresas-constructoras')
            ->middleware(['proveedor.access'])
            ->group(function () {

                // 🔍 Buscar empresas (por nombre, razón social o RFC)
                Route::get('/search', [EmpresaConstruccController::class, 'search']);

                // ✅ NUEVA RUTA: Obtener todas las empresas (sin paginación)
                Route::get('/all', [EmpresaConstruccController::class, 'all']);

                // 📋 Listado paginado de empresas
                Route::get('/', [EmpresaConstruccController::class, 'index']);

                // 🆕 Crear empresa y asociar a proveedor
                Route::post('/', [EmpresaConstruccController::class, 'store']);

                // 📝 Obtener detalle de una empresa
                Route::get('/{empresaConstrucc}', [EmpresaConstruccController::class, 'show']);

                // 👥 Obtener usuarios de una empresa
                Route::get('/{empresaConstrucc}/usuarios', [EmpresaConstruccController::class, 'usuarios']);

                // ✏️ Actualizar empresa existente
                Route::put('/{empresaConstrucc}', [EmpresaConstruccController::class, 'update']);

                // ❌ Desasociar o desactivar empresa
                Route::delete('/{empresaConstrucc}', [EmpresaConstruccController::class, 'destroy']);
            });
    });

/**
 * PEDIDOS DEL PROVEEDOR
 */
        // Route::prefix('{proveedor}/pedidos')->middleware(['proveedor.access'])->group(function () {
        //     Route::get('dashboard', [ProveedorPedidoController::class, 'dashboard'])->middleware(['audit'])->name('gerente.proveedor.pedidos.dashboard');
        //     Route::get('/', [ProveedorPedidoController::class, 'index'])->middleware(['audit'])->name('gerente.proveedor.pedidos.index');
        //     Route::get('{pedido}', [ProveedorPedidoController::class, 'show'])->middleware(['audit'])->name('gerente.proveedor.pedidos.show');
        //     Route::patch('{pedido}/status', [ProveedorPedidoController::class, 'updateStatus'])->middleware(['audit'])->name('gerente.proveedor.pedidos.update-status');
        //     Route::patch('{pedido}/prepare-shipment', [ProveedorPedidoController::class, 'prepareShipment'])->middleware(['audit'])->name('gerente.proveedor.pedidos.prepare-shipment');
        //     Route::patch('{pedido}/confirm-delivery', [ProveedorPedidoController::class, 'confirmDelivery'])->middleware(['audit'])->name('gerente.proveedor.pedidos.confirm-delivery');
        //     Route::patch('{pedido}/reject', [ProveedorPedidoController::class, 'rechazar'])->middleware(['audit'])->name('gerente.proveedor.pedidos.reject');
        //     Route::post('export', [ProveedorPedidoController::class, 'exportar'])->middleware(['audit'])->name('gerente.proveedor.pedidos.export');
        // });
