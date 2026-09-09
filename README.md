# API Proveedores - Documentación Completa del Proyecto

## 📋 Descripción
API backend para el sistema de gestión de proveedores de SJS Construcciones con sistema de notificaciones en tiempo real.

## 🚀 Requisitos Previos

- **Node.js** (versión 18 o superior)
- **PHP** (versión 8.2 o superior)
- **Composer** (gestor de paquetes de PHP)
- **Git** (control de versiones)
- **MySQL/MariaDB** (base de datos)
- **Laravel Reverb** (WebSocket server integrado - incluido en el proyecto)

## 📁 Estructura del Proyecto

```
api-proveedores/
├── src/
│   ├── app/
│   │   ├── Jobs/           # Jobs de Laravel para procesamiento asíncrono
│   │   └── pages/
│   │       └── proveedor/
│   │           └── csv-import/
│   │               └── pages/
│   │                   └── resultados-importacion/
├── notificaciones/         # Sistema de notificaciones
├── vendor/                 # Dependencias PHP (generado por Composer)
├── node_modules/           # Dependencias Node.js (generado por npm)
├── composer.json
├── package.json
├── start-soketi-env.ps1    # Script para iniciar Soketi
└── .env                    # Configuración de entorno
```

## 🔧 Instalación y Configuración Completa

### 1. Clonar el Repositorio
```bash
git clone [URL_DEL_REPOSITORIO]
cd C:\repositorio\sjsconstrucciones\app\api-proveedores
```

### 2. Instalar Dependencias de PHP
```bash
composer install
```
Este comando instalará:
- Laravel/Lumen framework
- Sistema de colas y jobs
- Librerías de notificación
- Pusher PHP Server
- Otras dependencias del backend

### 3. Instalar Dependencias de Node.js
```bash
npm install
```

### 4. Configurar Variables de Entorno

El archivo `.env` ya está configurado. Verifica estas variables clave:

```env
# Base de Datos
DB_CONNECTION=mysql
DB_HOST=*********
DB_PORT=3306
DB_DATABASE=db_proveedores
DB_USERNAME=user_db_proveedores
DB_PASSWORD=Sistemas789sjs

# Aplicación
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8088
APP_FRONTEND_URL=http://localhost:4300
APP_KEY=base64:/GwWRGgv9r5AiO1gXVxmJZWcZo7oW+aQEyrqiwkhVxA=

# Colas
QUEUE_CONNECTION=database
QUEUE_DRIVER=database

# Broadcasting con Reverb
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=887893
REVERB_APP_KEY=uosxzpdch98xbhpehcxx
REVERB_APP_SECRET=fvenkvletgtan8likynl
REVERB_HOST=************
REVERB_PORT=8080
REVERB_SERVER_HOST=*******
REVERB_SERVER_PORT=8080
REVERB_SCHEME=http
PUSHER_APP_CLUSTER=mt1
```

### 5. Configurar Base de Datos

```bash
# Ejecutar migraciones
php artisan migrate

# Crear tablas para jobs si no existen
php artisan queue:table
php artisan migrate

# Crear tablas para notificaciones
php artisan notifications:table
php artisan migrate
```

### 6. Laravel Reverb (WebSocket Server)

Reverb ya está instalado como dependencia del proyecto:

```bash
# Ver versión instalada
composer show laravel/reverb
```

## 🚀 Levantar el Proyecto Completo

### Opción 1: Inicio Rápido (Recomendado)

```bash
composer dev
```

Este comando inicia automáticamente:
- 👨‍💻 **Servidor Laravel** (localhost:8088)
- 📋 **Queue Workers** (imports, notifications, default)
- 📝 **Logs en tiempo real** (storage/logs/laravel.log)
- ⚡ **Vite** (assets frontend)
- 🔌 **Reverb WebSocket** (puerto 8080)

### Opción 2: Inicio Manual

**Terminal 1 - Servidor Laravel:**
```bash
php artisan serve --host 0.0.0.0 --port 8088
```

**Terminal 2 - Queue Workers:**
```bash
php artisan queue:work --queue=imports,notifications,default --sleep=3 --tries=3
```

**Terminal 3 - Reverb WebSocket:**
```bash
php artisan reverb:start
```

**Terminal 4 - Logs (Opcional):**
```powershell
Get-Content -Path ./storage/logs/laravel.log -Wait -Tail 10
```

**Terminal 5 - Vite (Si tienes assets):**
```bash
npm run dev
```

## 📊 Sistema de Jobs para Importación y Notificaciones

### Jobs Disponibles

1. **ImportProveedoresJob**
   - Procesa importación masiva de proveedores desde CSV
   - Ubicación: `app/Jobs/ImportProveedoresJob.php`
   
2. **ProcessNotificationJob**
   - Envía notificaciones en tiempo real a través de WebSockets
   - Ubicación: `app/Jobs/ProcessNotificationJob.php`

### Ejecutar Jobs Manualmente

```bash
# Procesar un job específico
php artisan queue:work --queue=import

# Procesar jobs de notificaciones
php artisan queue:work --queue=notifications

# Ver jobs pendientes
php artisan queue:failed
```

### Monitorear Jobs

```bash
# Ver estadísticas de la cola
php artisan queue:monitor notifications,import,default

# Reintentar jobs fallidos
php artisan queue:retry all
```

## 🔄 Flujo de Trabajo Completo

### Desarrollo Local - Orden de Inicio:

1. **Terminal 1 - Socket Server (Soketi):**
   ```powershell
   .\start-soketi-env.ps1
   ```
   Salida esperada:
   ```
   Starting Soketi server...
   Soketi is listening on port 6001
   ```

2. **Terminal 2 - Procesador de Jobs:**
   ```bash
   php artisan queue:work --queue=notifications,import,default --tries=3
   ```

3. **Terminal 3 - Servidor PHP:**
   ```bash
   php artisan serve --host 0.0.0.0 --port=8088
   ```

4. **Terminal 4 - Compilación Frontend (opcional):**
   ```bash
   npm run dev
   ```

## 🐛 Resolución de Problemas Comunes

### Problemas con Jobs

1. **Jobs no se procesan:**
   ```bash
   # Verificar configuración de colas
   php artisan config:clear
   php artisan cache:clear
   
   # Reiniciar worker
   php artisan queue:restart
   ```

2. **Jobs fallidos:**
   ```bash
   # Ver jobs fallidos
   php artisan queue:failed
   
   # Reintentar un job específico
   php artisan queue:retry [job-id]
   ```

### Problemas con Soketi/WebSockets

1. **Conexión rechazada:**
   - Verificar que Soketi esté corriendo
   - Verificar puerto 6001 no esté ocupado
   - Revisar configuración en `.env`

2. **No llegan notificaciones:**
   - Verificar que el queue worker esté activo
   - Revisar logs: `tail -f storage/logs/laravel.log`

### Errores de Compilación TypeScript

```bash
# Limpiar y reinstalar
rm -rf node_modules package-lock.json
npm cache clean --force
npm install
npm run build
```

## 📝 Scripts Disponibles

### PHP/Laravel
- `php artisan serve` - Servidor de desarrollo
- `php artisan queue:work` - Procesar jobs
- `php artisan queue:listen` - Procesar jobs con auto-reload
- `php artisan migrate` - Ejecutar migraciones
- `php artisan tinker` - Consola interactiva

### Node.js/NPM
- `npm run dev` - Modo desarrollo
- `npm run build` - Compilar para producción
- `npm run watch` - Observar cambios
- `npm run lint` - Verificar código

### PowerShell
- `.\start-soketi-env.ps1` - Iniciar Soketi con configuración

## 🌐 Endpoints Principales

- **API:** `http://localhost:8088/api`
- **WebSocket:** `ws://localhost:8080`
- **Documentación API:** `http://localhost:8088/api/documentation`
- **Front (app-proveedores):** `http://localhost:4300`

## 📋 Checklist de Verificación

- [ ] PHP y Composer instalados
- [ ] Node.js y NPM instalados
- [ ] Base de datos configurada y migraciones ejecutadas
- [ ] Archivo `.env` configurado correctamente
- [ ] Soketi instalado (`npm install -g @soketi/soketi`)
- [ ] Socket server corriendo (puerto 6001)
- [ ] Queue worker activo
- [ ] Servidor PHP activo (puerto 8000)

## 💡 Tips para Desarrollo

1. **Monitorear logs en tiempo real:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Limpiar caché cuando hay cambios de configuración:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan view:clear
   ```

3. **Test de WebSocket:**
   - Usar herramientas como Postman o wscat
   - Verificar en Chrome DevTools → Network → WS

4. **Supervisar Jobs:**
   - Usar Laravel Horizon (si está instalado)
   - O revisar tabla `jobs` en la base de datos

## 🚨 Importante

- **Siempre** iniciar Soketi antes que el queue worker
- **Siempre** ejecutar `npm run build` antes de deploy a producción
- **Nunca** commitear el archivo `.env` al repositorio
- **Verificar** que todos los servicios estén activos antes de probar

## 📞 Soporte

Para problemas o consultas sobre el proyecto, contactar al equipo de desarrollo.

---

**Última actualización:** Agosto 2025
**Versión:** 1.0.0
**Entorno:** Windows PowerShell
rutas necesarias para las ordendes de compra en construcc  (OC): 

- GET Listado de OC filtradas por: 
  - proveedor_id
  - estatus (que se puedan concatenar valores ejem: ?estatus=pendiente,pagado)
  - fecha (rangos de fecha) --- hoy, ayer, mañana, rango especifico
  // todos los filtros son independientes y deben poder aplicarse juntos


- GET Consulta OC by ID (Necesario validar si la OC pertenece al proveedor, parametros: proveedor_id, orden_compra_id)

// para los los post validar si el proveedor es el de la OC. Retornar error: No se cuenta con los permisos para este recurso.
- POST Estatus SP generada
- POST cancelada, rechazada, ...

Notificaciones: 
1. Al generarse una OC --> se registra en proveediore y genera notifcacion
2. Al generar SP apartir de una OC --> se debe notificar al usuario construcc de la SP
3. SP: al subir el comprobante de pago --> se debe notificar al proveedor del pago de la SP
4. SP: En caso de ser rechaza --> 
  4.1. Si rechaza proveedor --> motivo de rechazo y se notifica a usuairo construcc
  4.1. Si rechaza usuario construcc  --> motivo de rechazo y se notifica al proveedor



Para la app proveedores: 
1. Listar la OC pendientes de generar SP, 
2. Al seleccionar una Consutlar detalle para mostrar detalle (cambiar a pagina). 
  a. la consulta de la oc se realizara a consturcc
  b. las SP relacionadas se obtiene de api-proveedores
3. La generacion de SP apartir de una OC puede ser desde el dash en la pagiuna de detalle, o bien de las notificaciones (El link debe corresponder a la pp de proveedores) 



quiero que los servicios acutales los quietes y adaptes este flujo que te indico. Al menos concetrate en la parte Para la app proveedores. EL resto solo es contexto. La respouesta de listado de las OC es la siguiente 

{
    "status": "SUCCESS",
    "data": [
        {
            "NumOrden": "OC-000039",
            "Fecha": "2025-10-25T07:00:00.000000Z",
            "ProvID": 4,
            "importe": "348.00",
            "obraid": "346",
            "usuario": 75,
            "estatus": "pendiente",
            "Iva": "48.00",
            "Subtotal": "300.00",
            "observaciones": null,
            "tasa": "0.16",
            "reviso": null,
            "autorizo": null,
            "respobra": null,
            "dirigidoa": null,
            "utilizaen": null,
            "Fentrega": null,
            "Hentrega": null,
            "empresa": 14,
            "requisicion_id": "412",
            "tiene_requisicion": true,
            "tipo_orden": "REQUISICION",
            "created_at": "2025-10-25T01:18:07.000000Z",
            "updated_at": "2025-10-25T01:18:07.000000Z",
            "detalles": [
                {
                    "id": 39,
                    "NumOrden": "OC-000039",
                    "requisicion_detalle_id": 966,
                    "concepto": "CEMENTO GRIS 25 KG",
                    "unidad": null,
                    "cantidad_solicitada": "1.00",
                    "cantidad_autorizada": "1.00",
                    "cantidad_ordenada": "1.00",
                    "precio_unitario": "300.00",
                    "subtotal": "300.00",
                    "observaciones": null,
                    "created_at": "2025-10-25T01:18:07.000000Z",
                    "updated_at": "2025-10-25T01:18:07.000000Z"
                }
            ]
        },
    ]
}