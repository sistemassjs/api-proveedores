# Solicitudes de pago — Frontend

Repo: `app-proveedores`. Rutas en `proveedor-routing.module.ts`.

| Ruta app | Módulo |
|----------|--------|
| `/pages/proveedor/sp/*` | `solicitud-pago-proveedor/` |
| `/pages/proveedor/dashboard-oc-sp` | `home-proveedor/pages/dashboard-oc-sp/` |
| `/pages/proveedor/empresas` | `empresas-vinculadas/` |
| (módulo OC) | `ordenes-compra-proveedor/` |

## Flujo UI `solicitud-pago-proveedor`

Rutas: `list`, `sin-factura`, `historial`, `crear`, `editar/:id`, `detalle/:id`, `subir-factura/:id`.

Stepper: empresa/usuario → información → formas de pago → documentos.

Servicio: `solicitud-pago-proveedor/services/solicitud-pago-proveedor.service.ts`.

### Header del shell (obligatorio)

Todas las pages (y `historico-sp-proveedor` como ruta) siguen `app-proveedores/.cursor/rules/front-header-appstate.mdc`:

- `data.title` = `Solicitudes de pago` + subtítulo por pantalla en el routing
- `setHeader` en `ngOnInit` + `ionViewWillEnter`
- `clearHeader` (+ `clearItemCount` en listados) en `ionViewWillLeave` — nunca en `ngOnDestroy`

## Dashboard OC-SP

Tabs OC + SP, conversión OC→SP, histórico, métricas (`oc-sp-converter`, etc.).

## Notificaciones UI

Sección `solicitud_pago` en `@notificaciones` (deep-link a detalle).

## Cuentas bancarias (perfil)

Formulario: `perfil-usuario-proveedor/components/datos-bancarios-form/`.

Validación de `cuenta` (front + API): solo dígitos, **longitud libre**. CLABE = 18; tarjeta = 16.