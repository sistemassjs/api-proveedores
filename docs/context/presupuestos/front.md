# Presupuestos — Frontend

Repo: `app-proveedores`.

## Módulo principal

`src/app/pages/proveedor/presupuesto-proveedor/`

Montado en `proveedor-routing.module.ts` como ruta `presupuestos`.

| Área | Path relativo |
|------|----------------|
| Servicio documento | `services/presupuesto-proveedor.service.ts` (delega cartera/conceptos a módulos hijos) |
| Listados | `pages/presupuesto-proveedor-list` (enviados / recibidos / historial) |
| Form | `pages/presupuesto-proveedor-form` |
| Preview | `pages/presupuesto-proveedor-preview` |
| Enlace (auth) | `pages/presupuesto-enlace-publico` (`…/presupuestos/enlace-publico/:token`) |
| Stepper / modales | `components/presupuesto-form`, `presupuesto-page-modals` (también captura de **Plantillas** con `capturaMode: plantilla`) |
| Recursos CRUD (lazy) | `pages/{clientes\|catalogo-conceptos\|tarjetas}/` — module + routes + services + models + constants |
| Paths (barrel) | `constants/presupuesto-gestion.paths.ts` → reexporta paths de cada recurso |

Rutas UI: `/pages/proveedor/presupuestos/{list|recibidos|historial|crear|editar/:id|detalle/:id|preview/:id|clientes|…|catalogo-conceptos|…|tarjetas-presentacion|…}`.

## Header del shell (obligatorio)

Todas las `*.page.ts` del módulo siguen `app-proveedores/.cursor/rules/front-header-appstate.mdc`:

- `data.title` / `data.subtitle` en routing
- `setHeader` en `ngOnInit` + `ionViewWillEnter`
- `clearHeader` (+ `clearItemCount` en listados) en `ionViewWillLeave` — **nunca** en `ngOnDestroy`

## Menú y secciones (v1 — implementado)

Orden en `user-menu-new.ts`:

```text
Presupuestos
├── Generar Presupuesto       →  /pages/proveedor/presupuestos/crear
├── Plantillas                →  /pages/proveedor/presupuestos/plantillas
├── Mis presupuestos          →  /pages/proveedor/presupuestos/list
│                                 (segmentos internos: enviados | recibidos)
└── Recursos (colapsable)
    ├── Clientes              →  /pages/proveedor/presupuestos/clientes
    ├── Catálogo de conceptos →  /pages/proveedor/presupuestos/catalogo-conceptos
    └── Tarjetas Presentación →  /pages/proveedor/presupuestos/tarjetas-presentacion
                                  (Perfil → pestaña Tarjetas navega al listado)
```

Principios:

- Bajo Presupuestos: **Generar** / **Plantillas** / **Mis presupuestos** + grupo **Recursos** (Clientes, Conceptos, Tarjetas).
- **Generar presupuesto** entra **directo** al formulario de captura (`…/presupuestos/crear`). Ya no hay modal de “¿plantilla o desde cero?” al crear.
- **Plantillas** = receta reutilizable (CRUD en `pages/plantillas/`); **no** es el documento PPTO. Incluye: **nombre**, **conceptos**, **anexos img/PDF**, **tema**, **tarjeta**. **Sin** descripción general del presupuesto. Cards propias (`app-presupuesto-plantilla-card`, teñidas con `pdf_theme`). Captura crear/editar = `app-presupuesto-page-modals` con `capturaMode: 'plantilla'` (rutas en módulo padre; list/detalle lazy). Acción **Usar** (listado/detalle) → `aplicar` → borrador nuevo (`concepto_general` = Borrador) → `editar/:id`. Botón **Plantillas** en captura de PPTO → aplica la plantilla **al formulario/PPTO actual** (`aplicar-sobre` si hay id; merge en form si aún no) sin crear otro ni navegar; conserva cliente, fecha, nombre y descripción. **Guardar como plantilla** → modal con toggles → `desde-presupuesto` (sin copiar descripción general).
- Cada recurso de Gestión tiene **CRUD en pages propias** (patrón SPP: list / form / detail + components), **sin** embeber modales de captura.
- Captura (`crear` / `editar`) sigue eligiendo de esos recursos vía modales/selectores (snapshot).
- Dos entradas a Tarjetas: menú Presupuestos → Recursos + Perfil (misma ruta de listado).
- **Historial** en menú: fuera de v1.

Shell (menús): tema claro compartido móvil/desktop — ver `docs/context/platform-shared.md` (Shell UI) y `sidebar-menu-sections.scss`.

### Páginas de gestión (módulos lazy bajo `pages/{recurso}/`)

Cada recurso es un NgModule completo (`services`, `models`, `constants`, `pages`, `components`) con `loadChildren` desde `presupuesto-proveedor.routes.ts`. URLs públicas sin cambio.

| Recurso | Módulo | Path URL | Servicio |
|---------|--------|----------|----------|
| Plantillas | `pages/plantillas/presupuesto-plantillas.module.ts` | `…/plantillas` | `PresupuestoPlantillasService` |
| Clientes | `pages/clientes/presupuesto-clientes.module.ts` | `…/clientes` | `PresupuestoClientesService` |
| Catálogo conceptos | `pages/catalogo-conceptos/…` | `…/catalogo-conceptos` | `PresupuestoCatalogoConceptosService` |
| Tarjetas Presentación | `pages/tarjetas/…` | `…/tarjetas-presentacion` | `PresupuestoTarjetasService` (fachada sobre config perfil) |

Rutas hijas por módulo: `''` \| `crear` \| `editar/:id` \| `detalle/:id`. Patrón: regla `front-feature-crud.mdc` en app-proveedores.

UI: look alineado a cards/forms de captura (`styles/*-shared.scss` del recurso → `presupuesto-gestion-shared.scss`); no reutiliza el markup de los modales de captura.

Listados de gestión: barra search + toggle **detallada / mosaico** (constantes `presupuesto-gestion-view.constants.ts`), card con `[viewMode]`, conteo + `setItemCount`. Patrón: regla `front-listados.mdc` en app-proveedores.

## Config emisor/receptor (Tarjetas Presentación)

- API / servicio: `perfil-usuario-proveedor/services/proveedor-presupuesto-config.service.ts`
- UI de gestión: páginas `presupuesto-tarjetas-*` bajo Presupuestos
- Entradas: menú → Tarjetas Presentación; Perfil → pestaña **Tarjetas** (navigate a listado). Fragment `#presupuestos` → `…/tarjetas-presentacion`
- `PresupuestoConfigSharedModule` / `presupuesto-config-form` quedan como pieza de perfil/legacy; la casa de gestión es el CRUD de Tarjetas en Presupuestos

## Cuentas bancarias y pasarelas (perfil — cobro roadmap)

- UI: `perfil-usuario-proveedor/components/datos-bancarios-form/`
- Cuentas bancarias: implementadas.
- Sección **Servicios digitales**: PayPal y Stripe — UI placeholder (“No configurado” / Configurar); **sin integración real**.

## Público (sin login)

- `src/app/public/presupuesto-publico/`
- `src/app/public/visor-presupuesto-publico/`
- Rutas en `public-routing.module.ts`
- URL pública: `/public/presupuesto/{token_publico}` (param se llama `id` en la ruta, pero es el **token**, no la PK).

### Paridad de documento (preview pública ≈ preview empresa)

La vista HTML pública (y `enlace-publico`) alinea el documento con la preview de empresa:

- Tema: `pdf_theme_css` del API (`[ngStyle]` en cards; sin selector de temas).
- Totales con columnas de moneda + **importe con letra**.
- Anexos imagen paginados (4/página) + `titulo_anexos`.
- Motivo de rechazo en documento; receptor nombre → puesto → empresa (como PDF).
- Bloque Atentamente; modal de rechazo con opciones (igual que preview/enlace).

Anexos PDF solo en descarga PDF (merge), no en HTML. Acciones de emisor (editar, temas, historial) no aplican.

### Enlace público y QR del pie del PDF

- El QR del PDF y los correos usan `{APP_FRONTEND_URL}/public/presupuesto/{token}`.
- Si el usuario **tiene sesión** y abre esa URL, `publicPresupuestoAuthRedirectGuard` redirige a  
  `/pages/proveedor/presupuestos/enlace-publico/{token}` (vista dentro del shell; **no** a `preview/:id`, porque el param no es el id numérico).
- Sin sesión: se muestra `presupuesto-publico` con cabecera propia.
- Guard: `src/app/public/guards/public-presupuesto-auth-redirect.guard.ts`.
- Config API: `APP_FRONTEND_URL` (`config('app.frontend_url')`) debe apuntar al front, no a la API.

## Monedas (UI)

Campo `term_cond_moneda`: solo **MXN** \| **USD** \| **EUR** (default MXN). Prefijo de monto: `€` para EUR, `$` para MXN/USD.

## Ajustes del documento (fecha / layout / títulos de anexos)

### Captura de presupuesto (formulario principal)

- Card superior: **Información general del presupuesto** (icono assignment + engranaje).
  - **Información general**: abre `presupuesto-informacion-general-modal` (mismo sheet que el resto de modales; fondo `#f4f9f9` + cards). Header: título + **fecha compacta a la derecha** (popover calendario anclado al chip, con flecha; permite fechas futuras) + botón cerrar (círculo azul como descuento/anexo). Tres cards: **Dirigido a** (sin cliente → Catálogo / Agregar cliente; con cliente → solo la card con editar/quitar, sin botones Nuevo/Cambiar; `nombre_presupuesto` + `concepto_general` con contadores independientes estilo `descripcion-row`; descripción colapsada a 1 línea y se expande al enfocar), **Quién envía el presupuesto** (solo selector de tarjeta; sin input `empresa_emisora_nombre` en el modal) y **Estilo del documento** (tema PDF).
  - **Plantillas**: sheet `modal-plantillas-ppto` (mismo patrón visual que información general / ajustes: fondo `#f4f9f9`, header + cerrar circular, cards blancas, footer Cancelar). Listado de plantillas activas; al elegir, se aplican al **presupuesto actual** (conceptos, anexos, tema, tarjeta, títulos, IVA/descuento/términos) sin crear uno nuevo ni salir del formulario. Se conservan receptor, fecha, nombre y descripción general. El botón del form permanece como **Plantillas**; en el listado, la plantilla aplicada aparece **primera y resaltada**.
- Icono **settings**: abre `presupuesto-ajustes-modal` **solo** con mm de `ppto_config` (márgenes, gaps). La fecha **no** está en ajustes.
- Conceptos, totales, términos y anexos siguen en el formulario. Estilo y tarjeta de contacto **no** se muestran en el form de PPTO (sí en captura de plantilla).

### Fecha de emisión y `ppto_config`

- `fecha_emision` se edita en el modal **Información general** (popover anclado al chip; admite fechas futuras).
- `ppto_config`: defaults alineados a API; botón «Restaurar defaults» en ajustes.
- Listado: icono historial en cards (estado ≠ borrador) → sheet `estado_logs`. Preview: botón al final del documento (no en footer de acciones).
- **Duplicar**: modal sheet con header «Duplicar presupuesto»; switches (como cliente) para **cliente**, **anexos imagen**, **anexos PDF** y **tarjeta**; resumen (conceptos/total). Mientras corre la API el sheet permanece abierto con overlay de loading (sin toast de éxito). Cierra con cancelar, clic fuera o **arrastrar hacia abajo solo desde el handle** (bloqueados durante loading) → al terminar navega a `editar/:id`.
- **Solicitar aprobación / Enviar**: solo emisor (`esEmisorSesion` / `puedeEnviar`); el receptor ve Aceptar/Rechazar.
- Unidades e imagen en concepto (captura y catálogo CRUD): buscador + **Otro**; card imagen Reajustar / Cambiar / Quitar.
- Nombres propios Dirigido a / Atentamente: sentence case (`presupuesto-texto-documento.helper.ts`).
- Estilos overlay: `ion-modal.modal-ajustes-presupuesto`, `ion-modal.modal-informacion-general-ppto`, `ion-modal.modal-plantillas-ppto`, `ion-modal.modal-historial-presupuesto`, `ion-modal.modal-duplicar-presupuesto` y `ion-popover.fecha-picker-popover` en `src/global.scss`.

### Títulos de sección de anexos

| Campo | Card UI | Default | Dónde se ve en PDF / preview |
|-------|---------|---------|------------------------------|
| `titulo_anexos` | Anexos imagen (inline) | **Anexos** | Blade sección imágenes + preview (`tituloAnexosPreview`) |
| `titulo_anexos_pdf` | Anexos PDF (inline) | **Anexos PDF** | Estampado de hojas PDF mergeadas (mismo texto que el formulario) |

- Máx. 80 caracteres (alineado al API).
- Persistencia: borradores `tituloAnexosDraft` / `tituloAnexosPdfDraft` + `ngModel` standalone; commit al **blur**, al guardar borrador y antes de vista previa (`commitTituloAnexosDraft` / `commitTituloAnexosPdfDraft`). Evita truncado por autosave por tecla.
- Autosave: si hubo edición concurrente (`autosaveDrainPending`), **no** marcar pristine hasta drenar el valor completo.
- Preview imágenes: binding con `[textContent]` (no interpolación frágil).

### Límite de anexos imagen (solo front)

- Constante: `PRESUPUESTO_ANEXOS_IMAGEN_MAX = 4` en `helpers/presupuesto-anexo-imagen.helper.ts`.
- Al llegar al tope: dropzone deshabilitada (`anexos-dropzone--disabled`).
- Si el lote supera los cupos libres: se recorta y se avisa.
- La API **no** impone este tope.
- Motivo UX: una página de anexos imagen en PDF muestra 4 por hoja.

### PDF / preview — numeración

- Filas de concepto y párrafo: primera columna (`#`) centrada en preview (`presupuesto-proveedor-preview.page.scss`) y en blades PDF (`pdf.blade.php` / `pdf-tailwind.blade.php`).

## Folio en UI

- Muestra `numero_presupuesto` (`PRES-XXXX`) del API; el consecutivo lo lleva el backend por proveedor.

## Plan Plus — badge obligatorio en features Plus

Cuando una capacidad sea **Plus** (plan superior / no incluida en el esquema gratuito), la UI **debe** mostrar el badge con la directiva o el componente de `@theme`.

### Directiva (sobre un host, p. ej. botón)

```html
<button type="button" appPlanPlusBadge disabled>…</button>
<button type="button" appPlanPlusBadge="overlay">…</button>
<button type="button" appPlanPlusBadge="inline">…</button>
```

- Selector: `[appPlanPlusBadge]`
- Archivo: `src/app/@theme/directives/plan-plus-badge.directive.ts`
- Inputs: `planPlusBadgeLabel` (default `Plus`), `planPlusBadgeAriaLabel`
- `false` desactiva el badge; por defecto modo **overlay**

### Componente (badge suelto)

```html
<app-plan-plus-badge mode="inline"></app-plan-plus-badge>
<app-plan-plus-badge mode="overlay"></app-plan-plus-badge>
```

- Archivo: `src/app/@theme/components/plan-plus-badge/`
- Exportado vía `ThemeModule`

### Usos actuales en presupuestos (referencia)

- Tab / UI catálogo de conceptos: `<app-plan-plus-badge mode="inline">`
- Acciones de cartera / guardar en catálogo de clientes: `appPlanPlusBadge` en botones

**Regla para agentes:** si el requisito dice que algo es Plus → añadir `appPlanPlusBadge` o `<app-plan-plus-badge>` en la UI afectada.

## Ayuda en pantalla — tours interactivos (v1)

Tours contextuales con **Driver.js** (CDN en `src/index.html`, dependencia `driver.js` en `package.json`). Disparo desde el botón ℹ️ del `app-sub-header` cuando la página define `guideKey`.

### Alcance v1 (solo estos cuatro tours)

| `guideKey` | Pantalla | Qué recorre | Qué **no** incluye |
|------------|----------|-------------|-------------------|
| `plantillas-listado` | `presupuesto-plantillas-list` | buscar, vista, card, acciones, botón + | — |
| `plantillas-formulario` | captura plantilla (`presupuesto-page-modals`, `capturaMode: plantilla`) | datos, conceptos, estilo, anexos, tarjeta, guardar | modales (ajustes, concepto, etc.) |
| `presupuestos-listado` | `presupuesto-proveedor-list` | card (header, body, acciones) | tabs enviados/recibidos, filtros, FAB |
| `presupuestos-formulario` | captura PPTO (`presupuesto-page-modals`) | receptor, descripción, conceptos, totales, anexos, footer | modales |

Clientes, conceptos, tarjetas y preview **no** llevan `guideKey` en v1.

### Archivos

| Pieza | Ruta |
|-------|------|
| Claves y pasos | `src/app/@theme/constants/presupuestos-tutorials.config.ts` |
| Reexport tipos | `src/app/@theme/constants/presupuestos-ui-guides.ts` (`PresupuestoUiGuideKey` = alias de tour key) |
| Servicio | `src/app/@core/services/presupuesto-tutorial.service.ts` (`start`, `stop`; `disableActiveInteraction: true`) |
| Sub-header | `src/app/@theme/components/sub-header/` — `hasGuide` + `openGuide()` |
| Marcadores DOM | atributo `data-tour="…"` o `[attr.data-tour]` dinámico en plantilla vs PPTO |
| Estilos popover | `src/global.scss` — clases `driverjs-theme` / `presupuesto-tour-popover` |

### Marcadores `data-tour` (referencia)

- Plantillas listado: `plantillas-search`, `plantillas-view-mode`, `plantillas-card`, `plantillas-card-actions`, `plantillas-add` (`presupuesto-plantilla-card`, list page).
- Plantillas formulario: `plantilla-datos`, `plantilla-conceptos`, `plantilla-estilo`, anexos, `plantilla-tarjeta-contacto`, `plantilla-footer` (`presupuesto-page-modals`).
- Presupuestos listado: `ppto-card-header`, `ppto-card-body`, `ppto-card-actions` (`presupuesto-card`; primer card del listado).
- Presupuestos formulario: `ppto-receptor`, `ppto-descripcion`, `ppto-conceptos`, `ppto-totales`, `ppto-anexos`, `ppto-footer` (`presupuesto-page-modals`).

### Comportamiento

- `filterTourStepsPresent()` omite pasos cuyo selector no existe (p. ej. listado vacío sin cards → solo pasos intro sin `element`).
- Delay ~120 ms antes de `drive()` para dejar estabilizar el DOM tras navegación.
- **Scroll:** el formulario de captura usa `ion-content` sin scroll; el desplazamiento real es `.scrollable-area`. `PresupuestoTutorialService` llama `scrollTourElementIntoView()` en cada paso (`onHighlightStarted`) y `driver.refresh()` para recentrar el spotlight (misma util que listados: `scroll-container.util.ts`).
- No abre modales ni simula clics; texto de pasos lo indica cuando aplica.

### Añadir un tour nuevo

1. Extender `PresupuestoTourKey` y `buildPresupuestoTourSteps()` en `presupuestos-tutorials.config.ts`.
2. Añadir `data-tour` en el HTML de la pantalla.
3. Pasar `guideKey` al `app-sub-header` de la página.
4. Documentar aquí el alcance (qué incluye / excluye).

> **Legacy:** `UiGuideModalComponent` + PNG en `src/assets/guias/presupuestos/` quedaron sustituidos por tours; el manual completo sigue siendo el PDF (ver abajo).

## Manual PDF (menú usuario)

Manual estático de Presupuestos, **independiente** de los tours:

- PDF: `src/assets/manuales/presupuestos/manual-presupuestos.pdf`
- Fuente HTML: `src/assets/manuales/presupuestos/manual-presupuestos.html`
- Build: `npm run manual:presupuestos` → `tools/manuales/build-manual-presupuestos.js`
- Acceso UI: pie del menú usuario (`user-menu.component.ts`) → `DocumentViewerService.openPdf('assets/manuales/presupuestos/manual-presupuestos.pdf')`

Regenerar el PDF tras cambios de contenido en el HTML o imágenes bajo `src/assets/manuales/presupuestos/img/`.

## Catálogo de conceptos (Plus)

- API: `{proveedor}/presupuestos/presupuesto-catalogo-conceptos` (CRUD).
- Modal de concepto (hoy):
  - Tab Catálogo: listar / buscar / filtrar; click = snapshot a la línea; editar / eliminar; **Nuevo en catálogo**.
  - Tab Manual: checkbox «Guardar también en el catálogo» (+ categoría producto/servicio) al añadir línea. En **móvil**, el modal usa altura ~`96dvh` y el pie (CANCELAR / Añadir) queda **fijo fuera del scroll** para que no se oculte al marcar el checkbox.
- Badge Plus en tab y acciones de catálogo. El tab Catálogo también puede listar el **catálogo público** (origen `catalogo`, empresa/logo del Excel) junto a los conceptos internos; al elegir se hace snapshot. Editar/eliminar solo aplica a conceptos internos.
- Sección **Catálogo de conceptos** en rutas propias (`…/catalogo-conceptos` + crear/editar/detalle); el modal de captura sigue pudiendo elegir/snapshot.
