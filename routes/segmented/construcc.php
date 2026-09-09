<?php

use App\Enums\UserRoleEnumerate;
use App\Http\Controllers\ConstruccController;
use App\Http\Controllers\ConstruccCotizacionController;
use App\Http\Controllers\ConstruccSolicitudPagoController;
use App\Http\Controllers\ConstruccOrdenCompraController;
use App\Http\Controllers\ConstruccProveedorController;
use App\Http\Controllers\ConstruccProveedorCuentaBancariaController;
use App\Http\Controllers\ConstruccProveedorSolicitudPagoController;
use App\Http\Controllers\ConstruccPagosSPPController;
use App\Http\Controllers\ConstruccReportesController;
use App\Http\Middleware\CheckApiKey;
use Illuminate\Support\Facades\Route;

/**
 *--------------------------------------------------------------------------
 * RUTAS DEL MÓDULO DE CONSTRUCCIÓN
 *--------------------------------------------------------------------------
 * Rutas específicas para el sistema de construcción con autenticación API token.
 * Incluye búsquedas avanzadas, filtros múltiples y recursos paginados/no paginados.
 *
 * Estructura:
 * - Proveedores paginados con filtros
 * - Productos por proveedor paginados 
 * - Búsquedas avanzadas paginadas
 * - Recursos sin paginación para dropdowns
 *
 * Autenticación: API Token (Sanctum)
 * Auditoría: Habilitada en todas las rutas
 */

Route::prefix('construcc')
    // ->middleware(['auth:sanctum', 'role:' . UserRoleEnumerate::CONSTUCC_APP->value])
    ->middleware(CheckApiKey::class) // FIXME: Revisar autenticación mediante API Key.
    ->name('construcc.')
    ->group(function () {

        /*** 
         *--------------------------------------------------------------------------
         * SOLICITUDES DE PAGO - CRUD Completo
         *--------------------------------------------------------------------------
         * Gestión de solicitudes de pago con cambios de estatus y fechas
         */
        Route::prefix('solicitudes-pago')->name('solicitudes-pago.')->group(function () {

            // ✅ NUEVO: Generar solicitud de pago desde construcción (crea proveedor, cuenta bancaria y SPP)
            Route::post('generar-spp-construcc', [ConstruccSolicitudPagoController::class, 'generarSolicitudPagoConstrucc'])->name('generar-spp-construcc');

            // Listado y detalle (solo lectura en ConstruccApp)
            Route::get('/', [ConstruccSolicitudPagoController::class, 'index'])->name('index');

            Route::get('pendientes', [ConstruccSolicitudPagoController::class, 'pendientes'])->name('pendientes');

            Route::get('index-contadores', [ConstruccSolicitudPagoController::class, 'indexPorEstadoContadores'])->name('index-contadores');
            // Listado de SP no verificadas
            Route::get('no-verificadas', [ConstruccSolicitudPagoController::class, 'indexNoVerificadas'])->name('no-verificadas');

            Route::get('no-verificadas-para-ro', [ConstruccSolicitudPagoController::class, 'indexNoVerificadasParaRO'])->name('no-verificadas-para-ro');
            Route::get('no-verificadas-para-directores', [ConstruccSolicitudPagoController::class, 'indexNoVerificadasParaDirectores'])->name('no-verificadas-para-directores');

            // Listados especializados por rol y estado
            Route::get('por-rol', [ConstruccSolicitudPagoController::class, 'listarPorRol'])->name('por-rol');
            Route::get('por-estado', [ConstruccSolicitudPagoController::class, 'listarPorEstado'])->name('por-estado');
            Route::get('estadisticas-rol', [ConstruccSolicitudPagoController::class, 'estadisticasPorRol'])->name('estadisticas-rol');

            // metodos que se unificaran en el endpoint por-rol pero se dejan separados para evitar romper funcionalidades existentes en ConstruccApp

            // validada - 1  Y recibe cpom parametro: estauts- pendiente|autorizada  
            Route::get('sp-por-autorizar', [ConstruccSolicitudPagoController::class, 'spPendienteAutorizar'])->name('sp-por-autorizar');
            // validada - 0 y recibe parametro  usuario_id: entero no null, empresa_construcc_id: entero no null
            Route::get('sp-por-validar', [ConstruccSolicitudPagoController::class, 'spPorValidar'])->name('sp-por-validar');
            Route::get('sp-por-validar-otros', [ConstruccSolicitudPagoController::class, 'spPorValidarOtros'])->name('sp-por-validar-otros');
            Route::get('sp-sin-factura', [ConstruccSolicitudPagoController::class, 'spSinFactura'])->name('sp-sin-factura');

            // Endpoint para métricas generales del módulo de solicitudes de pago
            Route::get('metricas', [ConstruccSolicitudPagoController::class, 'metricas'])->name('metricas');





            // Segmento de dashboard para métricas de SP verificadas / no verificadas
            Route::prefix('dashboard-sp-metricas')->name('dashboard-sp-metricas.')->group(function () {
                Route::get('verificadas', [ConstruccSolicitudPagoController::class, 'dashboardSpMetricasVerificadas'])->name('verificadas');
                Route::get('no-verificadas', [ConstruccSolicitudPagoController::class, 'dashboardSpMetricasNoVerificadas'])->name('no-verificadas');
            });

            // Endpoints auxiliares
            Route::get('empresas-constructoras/search', [ConstruccSolicitudPagoController::class, 'empresasConstructoras'])->name('empresas-search');
            // ✅ NUEVO: Listar proveedores asociados a una empresa constructora
            Route::get('empresa/{empresaId}/proveedores', [ConstruccSolicitudPagoController::class, 'proveedoresPorEmpresa'])->name('proveedores-por-empresa');
            // ✅ NUEVO: Listar proveedores NO asociados a una empresa constructora
            Route::get('empresa/{empresaId}/proveedores/no-asociados', [ConstruccSolicitudPagoController::class, 'proveedoresNoAsociadosPorEmpresa'])->name('proveedores-no-asociados');
            // ✅ NUEVO: Asociar proveedor a una empresa constructora
            Route::post('empresa/{empresaId}/proveedores/asociar', [ConstruccSolicitudPagoController::class, 'asociarProveedorAEmpresa'])->name('asociar-proveedor');

            Route::get('estadisticas', [ConstruccSolicitudPagoController::class, 'estadisticas'])->name('estadisticas');
            Route::get('{solicitudPago}', [ConstruccSolicitudPagoController::class, 'show'])->name('show');

            // Gestión de archivos - Descargas protegidas
            Route::get('{solicitudPago}/comprobante/download', [ConstruccSolicitudPagoController::class, 'descargarComprobante'])->name('descargar-comprobante');
            Route::get('{solicitudPago}/factura-pdf/download', [ConstruccSolicitudPagoController::class, 'descargarFacturaPdf'])->name('descargar-factura-pdf');
            Route::get('{solicitudPago}/factura-xml/download', [ConstruccSolicitudPagoController::class, 'descargarFacturaXml'])->name('descargar-factura-xml');
            Route::get('{solicitudPago}/cotizacion/download', [ConstruccSolicitudPagoController::class, 'descargarCotizacion'])->name('descargar-cotizacion');

            // Gestion de archivos - Upload Factura XML/PDF 
            Route::post('{solicitudPago}/subir-factura', [ConstruccSolicitudPagoController::class, 'uploadFacturaPdfXml'])->name('subir-factura');

            // Cambios de estatus con validaciones por rol
            Route::post('{solicitudPago}/autorizar', [ConstruccSolicitudPagoController::class, 'autorizar'])->name('autorizar');
            Route::post('{solicitudPago}/autorizar-parcial', [ConstruccSolicitudPagoController::class, 'autorizarParcial'])->name('autorizar-parcial');
            Route::post('{solicitudPago}/rechazar', [ConstruccSolicitudPagoController::class, 'rechazar'])->name('rechazar');
            Route::post('{solicitudPago}/confirmar-pago', [ConstruccSolicitudPagoController::class, 'confirmarPago'])->name('confirmar-pago');
            Route::post('{solicitudPago}/actualizar-comprobante-pago', [ConstruccSolicitudPagoController::class, 'actualizarComprobantePago'])->name('actualizar-comprobante-pago');

            // Verificación de SP por usuario construcción
            Route::post('{solicitudPago}/marcar-verificada', [ConstruccSolicitudPagoController::class, 'marcarComoVerificada'])->name('marcar-verificada');
            Route::post('{solicitudPago}/marcar-rechazada', [ConstruccSolicitudPagoController::class, 'marcarComoRechazada'])->name('marcar-rechazada');
        });


        /**
         * CRUD completo de proveedores registrados por usuarios construcción,
         * sus cuentas bancarias y generación de solicitudes de pago
         * 
         * NOTE: 
         *      Se puede habilitar que el RO genere las SPP del proveedor, 
         *  esto solo implicaria deshabilitar la validacion de tipo_proveedor 2.
         *  Lo recomendado para este caso seria que se notificara al proveedor que se creo la SPP,
         *  esto solo para el caso de que el proveedor sea del tipo 1.
         */
        Route::prefix('proveedor')->name('proveedor.')->group(function () {

            /**
             * Búsqueda por criterio (GET, query params). Formato: success, message, data.
             * Parámetros: {criterio}=valor, empresa_id=ID (opcional)
             */
            Route::get('buscar-por-rfc', [ConstruccProveedorController::class, 'buscarPorRfc'])->middleware(['audit'])->name('buscar-por-rfc');
            Route::get('buscar-por-email', [ConstruccProveedorController::class, 'buscarPorEmail'])->middleware(['audit'])->name('buscar-por-email');
            Route::get('buscar-por-razon-social', [ConstruccProveedorController::class, 'buscarPorRazonSocial'])->middleware(['audit'])->name('buscar-por-razon-social');
            Route::get('buscar-por-telefono', [ConstruccProveedorController::class, 'buscarPorTelefono'])->middleware(['audit'])->name('buscar-por-telefono');

            /** 
             * GESTIÓN DE PROVEEDORES CONSTRUCCIÓN (tipo_alta = 2)
             */
            Route::get('/', [ConstruccProveedorController::class, 'index'])->name('index');
            Route::get('/{proveedor}', [ConstruccProveedorController::class, 'show'])->name('show');
            Route::post('/', [ConstruccProveedorController::class, 'store'])->name('store');
            Route::put('/{proveedor}', [ConstruccProveedorController::class, 'update'])->name('update');
            Route::delete('/{proveedor}', [ConstruccProveedorController::class, 'destroy'])->name('destroy');

            // ===== CUENTAS BANCARIAS =====
            Route::get('/{proveedor}/cuentas', [ConstruccProveedorCuentaBancariaController::class, 'index'])->name('cuentas.index');
            Route::get('/{proveedor}/cuentas/{cuenta}', [ConstruccProveedorCuentaBancariaController::class, 'show'])->name('cuentas.show');
            Route::post('/{proveedor}/cuentas', [ConstruccProveedorCuentaBancariaController::class, 'store'])->name('cuentas.store');
            Route::put('/{proveedor}/cuentas/{cuenta}', [ConstruccProveedorCuentaBancariaController::class, 'update'])->name('cuentas.update');
            Route::delete('/{proveedor}/cuentas/{cuenta}', [ConstruccProveedorCuentaBancariaController::class, 'destroy'])->name('cuentas.destroy');
            Route::post('/{proveedor}/cuentas/{cuenta}/set-favorita', [ConstruccProveedorCuentaBancariaController::class, 'setFavorita'])->name('cuentas.set-favorita');

            // ===== SOLICITUDES DE PAGO =====
            Route::post('/{proveedor}/solicitudes-pago', [ConstruccProveedorSolicitudPagoController::class, 'store'])->name('solicitudes-pago.store');

            /** 
             * GESTIÓN DE PROVEEDORES CONSTRUCCIÓN (tipo_alta = 1)
             */
            Route::get('/empresa/{empresaId}/all', [ConstruccProveedorController::class, 'allProveedores'])->name('proveedores.all');
            Route::get('/empresa/{empresaId}/all/con-spp', [ConstruccProveedorController::class, 'allProveedoresConSpp'])->name('proveedores.all.con-spp');
        });

        /**
         *--------------------------------------------------------------------------
         * PAGOS DE SOLICITUDES DE PAGO (SPP) - CRUD Completo
         *--------------------------------------------------------------------------
         * Gestión de pagos realizados a proveedores.
         * Un pago puede aplicar a múltiples solicitudes de pago.
         * Una solicitud de pago puede recibir múltiples pagos.
         */
        Route::prefix('pagos-spp')->name('pagos-spp.')->group(function () {

            // ===== GESTIÓN DE PROVEEDORES Y SUS SPP =====
            // GET /pagos/{pago}/descargar-comprobante -> Descargar comprobante de un pago
            Route::get('/pagos/{pago}/descargar-comprobante', [ConstruccPagosSPPController::class, 'descargarComprobantePago'])->name('proveedor.spp.descargar-comprobante');

            // POST /proveedor/{proveedor}/spp/{spp}/pagos/{pago}/subir-comprobante -> Subir comprobante de pago a una SPP específica
            Route::post('/proveedor/{proveedor}/spp/{spp}/pagos/{pago}/subir-comprobante', [ConstruccPagosSPPController::class, 'subirComprobanteSpp'])->name('proveedor.spp.pagos.subir-comprobante');

            // GET /proveedor/{proveedor}/spp/{spp}/pagos -> Listar pagos de una SPP de un proveedor
            Route::get('/proveedor/{proveedor}/spp/{spp}/pagos', [ConstruccPagosSPPController::class, 'pagosDeSpp'])->name('proveedor.spp.pagos');

            // GET /proveedor/{proveedor}/spp/{spp} -> Mostrar información de una SPP de un proveedor
            Route::get('/proveedor/{proveedor}/spp/{spp}', [ConstruccPagosSPPController::class, 'showSppProveedor'])->name('proveedor.spp.show');

            // GET /proveedor/{proveedor}/spp -> Listar SPP de un proveedor
            Route::get('/proveedor/{proveedor}/spp', [ConstruccPagosSPPController::class, 'sppPorProveedor'])->name('proveedor.spp.by-proveedor');


            // GET /proveedor -> Listar proveedores con SPP
            Route::get('/proveedor', [ConstruccPagosSPPController::class, 'indexProveedor'])->name('proveedor.spp.index');

            // GET /proveedor/{proveedor}/cuentas_bancarias -> Listar cuentas bancarias de un proveedor
            Route::get('/proveedor/{proveedor}/cuentas_bancarias', [ConstruccPagosSPPController::class, 'cuentasPorProveedor'])->name('proveedor.spp.cuentas');

            // ===== GESTIÓN DE PAGOS A PROVEEDOR =====
            // POST /proveedor/{proveedor}/pagos -> Registrar un pago a un proveedor
            Route::post('/proveedor/{proveedor}/pagos', [ConstruccPagosSPPController::class, 'registrarPagoProveedor'])->name('proveedor.pagos.registrar');

            // GET /proveedor/{proveedor}/pagos/{pago}/spp -> Listar SPP asociadas a un pago
            Route::get('/proveedor/{proveedor}/pagos/{pago}/spp', [ConstruccPagosSPPController::class, 'sppDePago'])->name('proveedor.pagos.spp');

            // ===== RUTAS GENERALES DE PAGOS =====
            // GET / -> Listar todos los pagos
            Route::get('/', [ConstruccPagosSPPController::class, 'index'])->middleware(['audit'])->name('index');

            // GET /{pago} -> Mostrar información de un pago específico
            Route::get('/{pago}', [ConstruccPagosSPPController::class, 'show'])->middleware(['audit'])->name('show');
        });

        /**
         *--------------------------------------------------------------------------
         * ESTADÍSTICAS Y REPORTES
         *--------------------------------------------------------------------------
         * Información agregada para dashboard y reportes
         */
        // Estadísticas generales del módulo
        Route::get('reportes/contabilidad', [ConstruccReportesController::class, 'reporteContabilidad'])->name('reporteContabilidad');



        /**
         *--------------------------------------------------------------------------
         * NOTAS DE IMPLEMENTACIÓN
         *--------------------------------------------------------------------------
         *
         * 1. FILTROS MÚLTIPLES:
         *    - Formato: ?categoria=1,2,3&marca=4,5
         *    - Se procesan como arrays en el controlador
         *
         * 2. PAGINACIÓN:
         *    - Parámetros: ?page=1&per_page=20
         *    - Máximo por página: 100
         *    - Por defecto: 20 elementos
         *
         * 3. ORDENAMIENTO:
         *    - Parámetros: ?sort_by=nombre&order=asc
         *    - Campos disponibles definidos en cada método
         *
         * 4. BÚSQUEDA:
         *    - Parámetro: ?buscar=termino
         *    - Busca en múltiples campos (nombre, descripción, etc.)
         *
         * 5. AUTENTICACIÓN:
         *    - Header: Authorization: Bearer {token}
         *    - Token generado con Sanctum
         *
         * 6. AUDITORÍA:
         *    - Todas las rutas registran actividad
         *    - Incluye usuario, acción y parámetros
         */

        /**
         *--------------------------------------------------------------------------
         * PROVEEDORES - Con Paginación
         *--------------------------------------------------------------------------
         * Lista de proveedores con filtros y búsqueda avanzada
         */
        Route::prefix('proveedores')->name('proveedores.')->group(function () {
            // Lista paginada de proveedores con filtros
            Route::get('/', [ConstruccController::class, 'proveedores'])
                ->middleware(['audit'])
                ->name('index');

            // Búsqueda avanzada de proveedores
            Route::get('buscar', [ConstruccController::class, 'buscarProveedores'])
                ->middleware(['audit'])
                ->name('buscar');

            // Productos de un proveedor específico (paginado)
            Route::get('{proveedor}/productos', [ConstruccController::class, 'productosPorProveedor'])
                ->middleware(['audit'])
                ->name('productos');

            // Búsqueda en productos de un proveedor específico
            Route::get('{proveedor}/productos/buscar', [ConstruccController::class, 'buscarProductosProveedor'])
                ->middleware(['audit'])
                ->name('productos.buscar');
        });

        /**
         *--------------------------------------------------------------------------
         * ÓRDENES DE COMPRA - CRUD Completo
         *--------------------------------------------------------------------------
         * Gestión de órdenes de compra desde el segmento construccción
         */
        Route::prefix('ordenes-compra')->name('ordenes-compra.')->group(function () {

            // Listado general y por filtros
            Route::get('/', [ConstruccOrdenCompraController::class, 'index'])->middleware(['audit'])->name('index');
            Route::get('/estadisticas', [ConstruccOrdenCompraController::class, 'estadisticas'])->middleware(['audit'])->name('estadisticas');

            // Crear nueva orden de compra
            Route::post('/', [ConstruccOrdenCompraController::class, 'store'])->middleware(['audit'])->name('store');

            // Operaciones sobre una orden específica
            Route::get('/{ordenCompra}', [ConstruccOrdenCompraController::class, 'show'])->middleware(['audit'])->name('show');
            Route::put('/{ordenCompra}', [ConstruccOrdenCompraController::class, 'update'])->middleware(['audit'])->name('update');
            Route::delete('/{ordenCompra}', [ConstruccOrdenCompraController::class, 'destroy'])->middleware(['audit'])->name('destroy');

            // Cambio de estado
            Route::post('/{ordenCompra}/cambiar-estado', [ConstruccOrdenCompraController::class, 'cambiarEstado'])->middleware(['audit'])->name('cambiar-estado');

            // Consultas por entidad
            Route::get('/proveedor/{proveedor}', [ConstruccOrdenCompraController::class, 'porProveedor'])->middleware(['audit'])->name('por-proveedor');
            Route::get('/empresa/{empresa}', [ConstruccOrdenCompraController::class, 'porEmpresa'])->middleware(['audit'])->name('por-empresa');
        });


        /**
         *--------------------------------------------------------------------------
         * PRODUCTOS - Con Paginación
         *--------------------------------------------------------------------------
         * Búsqueda general de productos con filtros avanzados
         */
        Route::prefix('productos')->name('productos.')->group(function () {
            // Búsqueda general de productos con filtros múltiples
            Route::get('buscar', [ConstruccController::class, 'buscarProductos'])
                ->middleware(['audit'])
                ->name('buscar');

            // Filtros disponibles para productos
            Route::get('filtros', [ConstruccController::class, 'filtrosProductos'])
                ->middleware(['audit'])
                ->name('filtros');

            // Sugerencias de productos para autocompletado
            Route::get('sugerencias', [ConstruccController::class, 'sugerenciasProductos'])
                ->middleware(['audit'])
                ->name('sugerencias');
        });

        /**
         *--------------------------------------------------------------------------
         * CATÁLOGOS - Sin Paginación (Para Dropdowns)
         *--------------------------------------------------------------------------
         * Recursos de catálogo específicos por proveedor sin paginación
         */
        Route::prefix('catalogos')->name('catalogos.')->group(function () {
            // Marcas de un proveedor específico
            Route::get('proveedores/{proveedor}/marcas', [ConstruccController::class, 'marcasProveedor'])
                ->middleware(['audit'])
                ->name('marcas');

            // Categorías de un proveedor (con/sin subcategorías)
            Route::get('proveedores/{proveedor}/categorias', [ConstruccController::class, 'categoriasProveedor'])
                ->middleware(['audit'])
                ->name('categorias');

            // Unidades de medida de un proveedor específico
            Route::get('proveedores/{proveedor}/unidades', [ConstruccController::class, 'unidadesProveedor'])
                ->middleware(['audit'])
                ->name('unidades');
        });

        Route::prefix('reportes')->name('reportes.')->group(function () {
            Route::get('estadisticas', [ConstruccController::class, 'estadisticas'])
                ->middleware(['audit'])
                ->name('estadisticas');

            // Resumen por proveedor
            Route::get('proveedores/{proveedor}/resumen', [ConstruccController::class, 'resumenProveedor'])
                ->middleware(['audit'])
                ->name('resumen');
        });

        /**
         *--------------------------------------------------------------------------
         * CONFIGURACIÓN Y METADATOS
         *--------------------------------------------------------------------------
         * Endpoints para configuración del módulo
         */
        Route::prefix('config')->name('config.')->group(function () {
            // Filtros disponibles globalmente
            Route::get('filtros-disponibles', [ConstruccController::class, 'filtrosDisponibles'])
                ->middleware(['audit'])
                ->name('filtros');

            // Opciones de ordenamiento disponibles
            Route::get('opciones-ordenamiento', [ConstruccController::class, 'opcionesOrdenamiento'])
                ->middleware(['audit'])
                ->name('ordenamiento');
        });

        /**
         *--------------------------------------------------------------------------
         * COTIZACIONES - CRUD Completo
         *--------------------------------------------------------------------------
         * Gestión de cotizaciones con sus detalles
         */
        Route::prefix('cotizaciones')->group(function () {
            Route::get('/', [ConstruccCotizacionController::class, 'index'])->middleware(['audit']);
            Route::post('/', [ConstruccCotizacionController::class, 'store'])->middleware(['audit']);
            Route::middleware(['audit'])->group(function () {
                Route::get('{cotizacion}', [ConstruccCotizacionController::class, 'show']);
                Route::patch('{cotizacion}', [ConstruccCotizacionController::class, 'update']);
                Route::delete('{cotizacion}', [ConstruccCotizacionController::class, 'destroy']);
            });
        });
    });
