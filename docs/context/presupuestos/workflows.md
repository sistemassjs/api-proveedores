# Presupuestos — Workflows

## Ciclo de vida objetivo

```
borrador → enviado → aceptar | rechazar(/con observación) → [reenviar si corrección] → … → pago → finalización
```

| Etapa | Estado en código | Notas |
|-------|------------------|-------|
| Borrador | `borrador` | Crear/editar; **sin** fila en `presupuesto_estado_logs` |
| Enviar | `enviado` | Primer log; emisor no edita mientras esté enviado |
| Aceptar | `aceptado` | Log; fechas derivadas del historial |
| Rechazar / pedir corrección | `rechazado` \| `rechazado_con_observacion` | Con motivo → observación/corrección; `nota` en log = motivo |
| Reenviar tras corrección | `enviado` | Desde rechazado(+motivo); limpia `motivo_rechazo`; nuevo log |
| Vencido | `vencido` | Cron `actualizarVencidos` también registra log |
| Pago | — | **Roadmap**: cuentas + pasarelas |
| Finalización | — | **Roadmap** |

## Flujo operativo (implementado)

1. **Borrador** — receptor (cartera \| proveedor registrado \| manual) + tarjeta emisor + conceptos + anexos + términos/descuento/moneda.
2. **Ajustes de documento (borrador)**
   - `fecha_emision` — modal Información general (permite futuras).
   - `ppto_config` — 8 medidas mm (márgenes, gaps logo/regla/footer/Atentamente); defaults en `PresupuestoPdfDocumentConfig`; UI modal Ajustes con «Restaurar defaults».
   - `titulo_anexos` / `titulo_anexos_pdf` — inline en cards anexos.
   - Anexos imagen: máximo 4 en captura (solo front).
3. **Enviar / Solicitar aprobación** — solo el **emisor** (`esEmisorSesion`); `enviar` también desde rechazo con observación / `enviar-correo` / `notificar-receptor-app` / `reenviar` (correo).
4. **Receptor** — ver sección **Notificaciones** (app vs correo según registro).
5. **Aceptar / rechazar** — preview autenticado o token público; rechazo con motivo → `rechazado_con_observacion`.
6. **Timeline** — sheet historial: icono en **cards del listado** + botón al **final del preview** (`estado_logs`).
7. **Duplicar** — en **Mis presupuestos** (no en recibidos): icono `copy` → modal (switches cliente / anexos imagen / anexos PDF / tarjeta + resumen) → `POST …/duplicar` (`mantener_cliente`, `mantener_anexos_imagen`, `mantener_anexos_pdf`, `mantener_tarjeta`) → nuevo borrador (folio y `fecha_emision` nuevos; términos/IVA/`term_cond_visibilidad`, `pdf_theme`/`ppto_config`; anexos se copian solo si el flag está activo, con archivos propios; limpia rechazo/visto) y navega a `editar/:id`. Distinto de **Plantillas**.
8. **Plantillas** — menú Presupuestos → Plantillas. Captura = misma UI que Generar PPTO (`presupuesto-page-modals`, sin cliente). **Usar** → `POST …/aplicar` → borrador sin receptor → editar. **Guardar como plantilla** (Mis presupuestos / preview emisor) → `POST …/desde-presupuesto/{id}` (copia todo excepto cliente). Lógica aislada del ciclo del documento.

## Notificaciones (presupuesto)

Código: `app/Notifications/Presupuesto/*` + copy compartido `app/Support/PresupuestoNotificationContent.php`.

### Contenido visible

| Superficie | Campo | Formato |
|------------|-------|---------|
| Bandeja del teléfono (Capacitor/FCM) y título del listado in-app | `titulo` | Oración: `{NombreTarjeta} envió el presupuesto {folio}.` (aceptado/rechazado/corrección usan destinatario; por vencer: `El presupuesto {folio} de {NombreTarjeta} está por vencer.`) |
| Cuerpo listado in-app + body push | `mensaje` | Corto y complementario al título (sin repetir quién/acción/folio). Ej.: total, emisión, motivo, vencimiento · descripción si hay |

Hechos siempre presentes en data: `usuario_envio_nombre`, `empresa_emisora_nombre`, `empresa_logo_url` (logo de la empresa **del actor** del evento: emisor en enviado/recibido/actualizado/por vencer; receptor/destinatario en aceptado/rechazado/corrección), `presupuesto_numero`, `presupuesto_titulo`, `fecha_emision`, `destinatario_nombre`.

### Cuándo se dispara cada una

| Notificación | Momento / disparador | Destinatarios | Canales típicos |
|--------------|----------------------|---------------|-----------------|
| **Recibido** (`PresupuestoRecibidoClienteProveedorNotification`) | Tras `enviar` (usuario principal del receptor catálogo) y/o `POST …/notificar-receptor-app`; también en `reenviar` (app) | Usuarios activos del **proveedor receptor** (o usuarios cuyo email = correo receptor y tienen otro proveedor ≠ emisor) | DB + broadcast + FCM |
| **Aceptado** (`PresupuestoAceptadoNotification`) | Cliente acepta (API pública o preview autenticado) | Creador del presupuesto (`user_id`), una sola vez | DB + broadcast + FCM (+ mail si aplica) |
| **Rechazado / Corrección** (`PresupuestoRechazadoNotification`) | Cliente rechaza; con motivo → copy de “Corrección” | Creador (`user_id`), una sola vez | DB + broadcast + FCM (+ mail) |
| **Cierre pendiente** (`PresupuestoCierrePendienteNotification`) | Cron `presupuestos:notificar-cierre-pendiente` (enviados que vencen **mañana**) | Usuarios activos del **proveedor emisor** | DB + broadcast + FCM (+ mail) |
| **Enviado** (`PresupuestoEnviadoNotification`) | Clase lista (estilo “emisor notificó envío”); hoy se usa en comandos QA/`dispatch-all`, **no** en el flujo principal de `enviar` | Emisor (cuando se despache) | DB + broadcast + FCM |

Acciones de bandeja (claves de título): enviado | recibido | actualizado | aceptado | rechazado | correccion | por_vencer.

### Casos de receptor: registrado vs no registrado

```text
Emisor solicita aprobación / envía
        │
        ▼
¿Receptor es proveedor del catálogo (proveedor_receptor_id / flag)?
   │                              │
  SÍ                             NO
   │                              │
   ▼                              ▼
Notificación in-app          ¿Email receptor coincide con
(Recibido) a usuarios         User en otro proveedor activo?
del proveedor receptor            │              │
(+ opcional correo               SÍ             NO
 vía enviar-correo)               │              │
                                  ▼              ▼
                           Notificación     Sin push/campana.
                           Recibido a       Solo correo
                           esos users       PresupuestoEnviadoMail
                                            (enlace público / QR)
                                            ± invitación a registrarse
```

- **Registrado en app (otro proveedor):** push + listado in-app (“recibidos”); puede abrir preview autenticado.
- **No registrado:** no hay `PresupuestoRecibido…`; se usa **correo** (`PresupuestoEnviadoMail`) con enlace `/public/presupuesto/{token}`. Aceptar/rechazar vía token público; el emisor sí recibe Aceptado/Rechazado in-app.
- **Duplicados cruzados:** si un user también pertenece al emisor, se **excluye** de la notificación de receptor (evita doble campana).
- Al abrir el preview de un recibido, se marcan leídas las notificaciones `PresupuestoRecibido…` de ese presupuesto (badge).

## PDF y personalización

- Preview sin preguntar guardar (flujo page-modals: autosave si dirty).
- `config_mostrar_totales` oculta totales + IVA + importe con letra.
- **QR pie**: `{APP_FRONTEND_URL}/public/presupuesto/{token}` — debe coincidir con ruta front; con sesión → `enlace-publico/:token`.
- Márgenes/gaps: variables Blade desde config + override `ppto_config`.
- Icono marca: app sin borde (`assets/icon` / `presupuestos/app-sin-borde`); impresión/PDF con borde (`logo-gestionplus_only_blade.png` / `presupuestos/impresion-con-borde`).
- Nombres propios Dirigido a / Atentamente: sentence case (1ª mayúscula) en helper front.

## Emisor: usuario vs empresa

La config emisor/receptor permite datos de **contacto/usuario emisor** distintos al snapshot de **empresa emisora** (logo, razón social, etc.). Se elige tarjeta al armar el presupuesto.

Gestión en sección **Tarjetas Presentación** (list / form / detail); Perfil (pestaña Tarjetas) enlaza al listado. En captura solo se elige tarjeta (snapshot al documento).

## Cobro (roadmap — no es dominio SP)

Tras aceptación, la visión es cobro/finalización **dentro de presupuestos**:

1. **Cuentas bancarias** — ya en perfil empresa (`datos-bancarios-form`); el presupuesto podrá referenciarlas.
2. **Pasarelas** — PayPal y Stripe: sección “Servicios digitales” en el mismo perfil; botones Configurar; **aún no implementadas**.
3. **No** convertir el presupuesto en Solicitud de pago (SP) salvo decisión futura explícita (hoy: dominios aislados).

## Cartera (Clientes)

- Alta/edición también en modal de selección de cliente al crear presupuesto.
- Guardar receptor en cartera / ciertas acciones pueden ser **Plus** (badge en UI).
- Sección menú **Recursos → Clientes**: list / form / detail propios (`presupuesto-clientes-*`). En captura se elige de la cartera (o manual / proveedor registrado).

## Catálogo de conceptos

- CRUD embebido en modal de línea (tab Catálogo + “guardar también en catálogo” en Manual); feature **Plus**.
- Sección menú **Recursos → Catálogo de conceptos**: list / form / detail propios (`presupuesto-catalogo-conceptos-*`); al usarlo en el presupuesto se hace **snapshot** a la línea (sin FK).
