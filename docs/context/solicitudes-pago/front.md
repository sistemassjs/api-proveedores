# Solicitudes de pago — Frontend

Repo: `app-proveedores`. Rutas en `proveedor-routing.module.ts` y panel admin.

| Ruta app | Módulo |
|----------|--------|
| `/pages/proveedor/sp/*` | `solicitud-pago-proveedor/` |
| `/pages/proveedor/dashboard-oc-sp` | `home-proveedor/pages/dashboard-oc-sp/` |
| `/pages/proveedor/empresas` | `empresas-vinculadas/` |
| (módulo OC) | `ordenes-compra-proveedor/` |
| `/pages/panel-admin/spp` | `panel-administrativo/pages/spp/` (listado admin documentos) |
| `/pages/panel-admin/spp/detail/:id` | Detalle admin + descargas de anexos |

## Flujo UI `solicitud-pago-proveedor`

Rutas: `list`, `sin-factura`, `historial`, `crear`, `editar/:id`, `detalle/:id`, `subir-factura/:id`.

Stepper: empresa/usuario → información → formas de pago → documentos.

Servicio: `solicitud-pago-proveedor/services/solicitud-pago-proveedor.service.ts`.

### Header del shell (obligatorio)

Todas las pages (y `historico-sp-proveedor` como ruta) siguen `app-proveedores/.cursor/rules/front-header-appstate.mdc`:

- `data.title` = `Solicitudes de pago` + subtítulo por pantalla en el routing
- `setHeader` en `ngOnInit` + `ionViewWillEnter`
- `clearHeader` (+ `clearItemCount` en listados) en `ionViewWillLeave` — nunca en `ngOnDestroy`

## Panel admin — Documentos SPP

Consulta (solo lectura) de SPP y anexos para rol `ADMINISTRADOR`.

| Pieza | Ubicación |
|-------|-----------|
| Menú | `user-menu-new.ts` → **Documentos SPP** |
| Listado | `panel-administrativo/pages/spp/pages/spp-list/` — cards compactas `app-sp-dashboard-card` con chips PDF/XML/COT/COMP, proveedor, empresa construcc y `usuario_nombre` |
| Filtros | Empresa (`proveedor_id`), folio (`search`), rango fechas, estado — patrón toolbar/drawer admin |
| Detalle | `spp-detail` — resumen + descarga de documentos vía `SolicitudesPagoAdminService` |
| Entrada desde empresa | Detalle empresa → Actividad → **Ver todas** / click card → `/panel-admin/spp?proveedorId=` o `detail/:id` |
| API | `GET /admin/solicitudes-pago` (+ show / descargas) |

Card: `@Input() showDocumentChips`, `showProveedor`, `showEmpresaConstrucc` en `sp-dashboard-card`.

## Dashboard OC-SP

Tabs OC + SP, conversión OC→SP, histórico, métricas (`oc-sp-converter`, etc.).

## Notificaciones UI

Sección `solicitud_pago` en `@notificaciones` (deep-link a detalle).

## Cuentas bancarias (perfil)

Formulario: `perfil-usuario-proveedor/components/datos-bancarios-form/`.

Validación de `cuenta` (front + API): solo dígitos, **longitud libre**. CLABE = 18; tarjeta = 16.
