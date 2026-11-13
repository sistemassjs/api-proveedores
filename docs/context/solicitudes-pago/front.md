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

## Modelo mental UI (Melio / QBO / BILL)

Separar dinero **entrante** vs **saliente** (como Melio Pay / Get paid y QBO Pay Bills / Sales):

| Lado | Ruta | Rol de la empresa activa |
|------|------|---------------------------|
| **Por cobrar** | `/pages/proveedor/sp/list` | Emisora / cobradora |
| **Por pagar** | `/pages/proveedor/sp/recibidas` | Pagadora (canal plataforma) |
| **Actividad** | `/pages/proveedor/sp/historial` | Historial unificado |

Shell compartido: `styles/sp-shell.scss` + `app-sp-lado-switch` (`Por cobrar | Por pagar | Actividad`) en listados.  
Cards: `sp-dashboard-card` bill-row con `contextoLista` (`cobrar` | `pagar`) → copy de estado distinto.  
Empty: `app-sp-empty-state` plano.  
Detalle: `modoPagadora` cambia textos (quién paga/cobra, timeline, acciones).

### Cola Por pagar (BILL Approvals → Melio Ready to pay)

Tabs: **Autorizar** (`pendiente`) → **Pagar** (`autorizada`) → **Pagadas** → **Rechazadas** → **Todas**.  
Default: Autorizar. Detalle con `?modo=recibida` y acciones sticky Autorizar / Pagar / Rechazar(+motivo).

### Menú (`user-menu-new.ts`)

```text
Solicitudes de pago
├── Crear solicitud
├── Por cobrar
├── Por pagar
└── Actividad
```

Sin marca “GestionPlus” en el menú. Canal en chip de card: **Construcc** | **Plataforma**.

### Home SP

Tiles: Nueva · Por cobrar · Por pagar (badge pendientes) · Actividad · Incompletas.

## Flujo UI `solicitud-pago-proveedor`

Rutas: `list`, `recibidas`, `sin-factura`, `historial`, `crear`, `editar/:id`, `detalle/:id`, `subir-factura/:id`.

Stepper create: canal **Construcc** | **Otra empresa** → información → formas de pago → documentos.

Servicio: `solicitud-pago-proveedor/services/solicitud-pago-proveedor.service.ts`  
(`fetchRecibidas`, `autorizarRecibida`, `rechazarRecibida`, `pagarRecibida`, `searchEmpresasGestionPlus`).

### Header del shell (obligatorio)

Todas las pages (y `historico-sp-proveedor` como ruta) siguen `app-proveedores/.cursor/rules/front-header-appstate.mdc`:

- `data.title` = `Solicitudes de pago` + subtítulo (`Por cobrar` / `Por pagar` / `Actividad` / …)
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

Card: `@Input() showDocumentChips`, `showProveedor`, `proveedorLabel`, `showEmpresaConstrucc` en `sp-dashboard-card`.

Chip COMP / `url_comprobante_pago`: el API resuelve comprobante desde la SPP **o** el último `PagoSPP` asociado (no asumir solo `ruta_archivo_comprobante_pago` vacío = sin archivo). En detalle, preferir también la lista `pagos[].datos_comprobante`.

## Dashboard OC-SP

Tabs OC + SP, conversión OC→SP, histórico, métricas (`oc-sp-converter`, etc.).

## Notificaciones UI

Sección `solicitud_pago` en `@notificaciones`.  
Recibida plataforma: `action_url` con `?modo=recibida`; `proveedor_id` = empresa pagadora (filtro multiempresa).

## Cuentas bancarias (perfil)

Formulario: `perfil-usuario-proveedor/components/datos-bancarios-form/`.

Validación de `cuenta` (front + API): solo dígitos, **longitud libre**. CLABE = 18; tarjeta = 16.
