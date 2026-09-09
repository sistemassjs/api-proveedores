<?php

/*
|--------------------------------------------------------------------------
| ARCHIVO PRINCIPAL DE RUTAS SEGMENTADAS
|--------------------------------------------------------------------------
| Este archivo incluye todos los archivos de rutas organizados por roles
| y funcionalidades específicas
|
| Estructura:
| - public.php: Rutas públicas sin autenticación
| - auth.php: Rutas de autenticación protegidas
| - shared.php: Recursos compartidos por todos los roles
| - cliente.php: Rutas específicas para rol CLIENTE
| - gerente.php: Rutas específicas para rol GERENTE (proveedor)
| - admin.php: Rutas específicas para rol ADMINISTRADOR
| - mixed.php: Rutas para roles mixtos
| - middleware.php: Rutas con middleware especializados
| - notifications.php: Rutas de notificaciones
| - compatibility.php: Rutas de compatibilidad con código existente
| - testing.php: Rutas de desarrollo y testing
*/

/*
|--------------------------------------------------------------------------
| ORDEN DE CARGA DE ARCHIVOS DE RUTAS
|--------------------------------------------------------------------------
| Es importante mantener este orden para evitar conflictos
*/

// 0.1. Módulo de construcción (con autenticación API token)
require __DIR__ . '/construcc.php';

// 1. Rutas públicas (sin autenticación)
require __DIR__ . '/public.php';

require __DIR__ . '/puricadora_colibri.php';

// 2. Rutas de autenticación protegidas
require __DIR__ . '/auth.php';

// 3. Recursos compartidos (todos los roles autenticados)
require __DIR__ . '/shared.php';

// 4. Rutas específicas por rol
require __DIR__ . '/cliente.php';
require __DIR__ . '/gerente.php';
require __DIR__ . '/admin.php';

// 5. Rutas para roles mixtos
require __DIR__ . '/mixed.php';

// 6. Rutas con middleware especializados
require __DIR__ . '/middleware.php';

// 7. Rutas de notificaciones especializadas (ELIMINADO)
require __DIR__ . '/notifications.php';

// 8. Rutas de compatibilidad (mantienen comportamiento existente)
require __DIR__ . '/compatibility.php';

// 9. Rutas de desarrollo y testing (solo en local/testing)
require __DIR__ . '/testing.php';

/*
|--------------------------------------------------------------------------
| NOTAS IMPORTANTES
|--------------------------------------------------------------------------
| 
| 1. Las rutas de compatibilidad mantienen exactamente el mismo comportamiento
|    que las rutas originales para no romper funcionalidad existente.
|
| 2. Las nuevas rutas segmentadas por rol proporcionan una estructura más clara
|    y facilitan el mantenimiento futuro.
|
| 3. Se mantienen los middleware existentes y se respetan los permisos de acceso.
|
| 4. Todas las rutas mantienen el middleware de auditoría donde corresponde.
|
| 5. Los nombres de rutas se mantienen para compatibilidad, pero se añaden
|    nuevos nombres más descriptivos para las rutas segmentadas.
*/
