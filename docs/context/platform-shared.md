# Plataforma compartida (no es un dominio de negocio)

Infraestructura que usan los tres dominios. **No expandir** como “módulo núcleo” ni meter lógica de catálogo/SP/presupuestos aquí.

## Stack

- **API:** Laravel, Sanctum, Eloquent — repo `api-proveedores`
- **App:** Angular + Ionic PWA — repo `app-proveedores`
- **Contrato JSON:** `status`, `code`, `message`, `data`, `errors` (`ApiResponse`)

## Alcance mínimo

| Pieza | Dónde | Uso |
|-------|--------|-----|
| Auth / sesión | `routes/segmented/auth.php`, front `@auth/` | Login, token, `/auth/me` |
| **Login social (OAuth)** | [platform-auth-socialite.md](./platform-auth-socialite.md) | Google vía Socialite + Sanctum; multi-provider listo |
| Proveedor | Model `Proveedor`, prefijo `proveedores/{proveedor}/…` | Contenedor multi-proveedor |
| **Perfil empresa completado** | `ProveedorPerfilCompletadoService`, `GET …/perfil-completado` | Bandera `perfil_empresa_completo`, banner Mi Empresa, OAuth `pending_registro` |
| Acceso | `tieneAccesoAProveedor`, middleware `proveedor.access` / `api.access` | Autorización por proveedor |
| Roles | `UserRoleEnumerate` (ADMINISTRADOR, GERENTE, SUPERVISOR, VENTAS, AUXILIAR, …) | Rutas segmentadas + menú |
| Usuarios / matriz MVP | [platform-users-roles.md](./platform-users-roles.md) | Gestión empresa: principal GERENTE, roles asignables SUP/VEN/AUX |
| Métricas de plataforma | Sección siguiente + [platform-users-roles.md](./platform-users-roles.md#métricas-y-cuentas-de-pruebas) | Totales / actividad: excluye roles internos y cuentas/empresas de pruebas |
| Storage / mail / FCM | Traits, Mail, Notifications genéricas | Archivos, correo, push |
| Shell menús (front) | `app-sidebar-menu` / `app-desktop-sidebar` | Dos menús distintos; ver sección siguiente |
| **Perfil público** | Sección siguiente | Página de presentación compartible por enlace |

## Shell UI (menús)

Hay **dos menús** (móvil drawer / escritorio rail) con **la misma línea visual y paleta**:

| Menú | Componente |
|------|------------|
| Móvil | `app-sidebar-menu` |
| Escritorio | `app-desktop-sidebar` |

Estructura común (ambos: `app-sidebar-menu` + `app-desktop-sidebar`; `sidebar-menu-sections.scss`):

1. **Cabecera** charcoal más oscuro `#222428` (texto blanco)
2. **Cuerpo** claro `#f4f9f9` — ítems legibles, activo primary
3. **Pie** `#2a2e34` (oscuro pero más claro que la cabecera): Invitar / Compartir + tarjeta usuario (con margen)

Sin hueco menú–contenido en desktop.

### Título del toolbar (AppStateService)

El shell muestra `appName$` + `headerSubtitle$`. Contrato obligatorio en pages (presupuestos, Mi Empresa, Mi Perfil, usuarios, etc.): regla Cursor `front-header-appstate.mdc` — `setHeader` en init/WillEnter, `clearHeader` en WillLeave (nunca en Destroy).

Al leer `route.data` en cadena de padres: **priorizar la ruta hoja**; no dejar que un padre genérico (`Proveedor`) pise títulos específicos (`Mi Empresa`, `Inicio`, …).

| Pantalla | title (routing) | subtitle tipico |
|----------|-----------------|-----------------|
| Mi Empresa | Mi Empresa | Perfil de la empresa (o segmento activo) |
| Perfil público | Perfil público | Qué ven quienes reciben tu enlace |
| Mi Perfil | Mi Perfil | Datos de usuario |
| Inicio | Inicio | Panel de control / nombre del proveedor |
| Solicitudes de pago | Solicitudes de pago | listado, historial, crear, detalle… |
| Catálogo productos | Productos / Marcas / Categorías / Importación | según cada routing |
| Presupuestos | según `presupuesto-proveedor.routes.ts` / recursos | listado, crear, preview, clientes… |
| Dashboard Admin | Dashboard | Panel administrativo |

## Métricas de plataforma (operativo)

KPIs de producto (totales de usuarios/empresas, usuarios activos, series diarias, Looker, Pulse) **no** miden operación interna ni datos de QA.

**Quedan fuera** si aplica cualquiera de:

1. **Rol interno / integración:** `ADMINISTRADOR`, `CONSTRUCC_APP`, `ventas_purificadora_colibri`
2. **Cuenta de pruebas (usuario):** flag `es_cuenta_de_pruebas` en el usuario (cualquier rol de negocio)
3. **Empresa de pruebas (proveedor):** flag `es_cuenta_de_pruebas` en el proveedor — se marca desde el **formulario admin de la empresa**; excluye la empresa de totales y la actividad bajo ese `proveedor_id`; usuarios vinculados a esa empresa tampoco entran en totales de usuarios

El flag es de negocio/operación (marcar QA, demos, sandboxes), no un permiso. El listado admin de usuarios registrados usa otro criterio (whitelist de roles de producto); ver [platform-users-roles.md](./platform-users-roles.md#métricas-y-cuentas-de-pruebas).

La lista de roles excluidos / visibles y el cableado de queries viven en código (`config/metricas_plataforma.php`); este contexto solo fija **quién cuenta / quién se lista**.

## Perfil de empresa completado (Mi Empresa)

Servicio canónico: `App\Services\Proveedor\ProveedorPerfilCompletadoService`.

| Concepto | Regla |
|----------|--------|
| **Bandera BD** `proveedores.perfil_empresa_completo` | `nombre_comercial` + `email` + `razon_social` + `rfc` |
| **Cuenta bancaria** | Opcional para la bandera (ícono informativo `bancarios` en front) |
| **Constancia fiscal** | **No** cuenta para la bandera; **sí** para `puedeGenerarSP` |
| **Logo** | Opcional |

**Revalidación:** tras guardar/eliminar datos en Mi Empresa, la API recalcula la bandera en:

- `ProveedorController::update`
- `ProveedorController::updateConstanciaFiscal`
- CRUD `ProveedorCuentaBancariaController` (store / update / destroy)

Endpoints de consulta (siempre evalúan en vivo, sin atajos por bandera cacheada):

- `GET proveedores/{proveedor}/perfil-completado` — banner sidebar + segmentos Mi Empresa
- `GET proveedores/{proveedor}/puede-generar-sp` — crear Solicitud de Pago

Front: `PerfilValidacionStateService.refresh(proveedorId, { force: true })` tras guardar/eliminar en formularios de perfil (generales, fiscales, constancia, bancarios).

OAuth / registro: `pending_registro=1` si falta `perfil_empresa_completo` o `registro_completado_at` — ver [platform-auth-socialite.md](./platform-auth-socialite.md).

## Perfil público (compartir información de la empresa)

**Plataforma**, no es un dominio de negocio. Un solo enlace activo por proveedor; el gerente elige qué secciones publicar.

| Pieza | Dónde |
|-------|--------|
| Tabla | `proveedor_perfil_publico` (token opaco, `theme_key`, `sections`, `snapshot`, `is_published`) |
| API auth | `proveedores/{proveedor}/perfil-publico` — GET / PUT / POST `publicar` / POST `despublicar` / GET `themes` |
| API pública | `GET /public/perfil/{token}` (throttle) |
| Temas | `App\Services\PerfilPublico\PerfilPublicoThemeService` (paletas solo para **cards/contenido de empresa**; cabecera de marca + invite usan identidad fija GestionPro `#006eff` / `#222428`) |
| Snapshot | `PerfilPublicoSnapshotBuilder` — congela solo lo marcado al publicar/actualizar |
| Front editor | `app-proveedores` → `pages/proveedor/perfil-publico/` · ruta UI `/pages/proveedor/perfil-publico` · menú **Perfil público** |
| Front público | `/public/perfil/{token}` · invite a `/reg` en el snapshot |

Secciones configurables: empresa, contacto, tarjetas (configs emisor), bancos, fiscal + constancias (`constancias[]`; hoy suele haber una con id virtual `principal`).

UX obligatoria: avisos claros de que el contenido **será público** (sin login); confirmación extra al publicar con bancos/fiscal. El borrador se guarda al editar; el enlace público solo cambia con **Publicar / Actualizar**.

Compartir: cada sección del preview y el perfil completo pueden enviarse por WhatsApp como **mensaje estructurado** (no solo el link). Desde **Mi Empresa** hay atajo (abrir Perfil público / compartir WhatsApp).

Pie del menú (Invitar / Constancia): coexisten por ahora; la superficie canónica de “compartir datos de empresa” es Perfil público.

Migración: `database/migrations/*_create_proveedor_perfil_publico_table.php` (ejecutar en cada entorno).

## Qué no va aquí

- Productos, categorías, marcas → [catalogo](./catalogo/)
- Solicitudes de pago, facturas, OC → [solicitudes-pago](./solicitudes-pago/)
- Presupuestos, conceptos, cartera de presupuesto → [presupuestos](./presupuestos/)

## Rutas segmentadas (mapa)

| Archivo | Contenido típico |
|---------|------------------|
| `routes/segmented/auth.php` | Autenticación |
| `routes/segmented/gerente.php` | API del gerente/proveedor (mezcla prefijos de los 3 dominios; aislar por path) |
| `routes/segmented/admin.php` | Panel administrador |
| `routes/segmented/public.php` | Endpoints públicos |
| `routes/segmented/construcc.php` | Consumidor externo (sobre todo SP + algo de catálogo) |
| `routes/segmented/notifications.php` | Webhooks / notificaciones |

Aunque `gerente.php` agrupe varios prefijos, **cada prefijo pertenece a un solo dominio**.
