# Mapa de pendientes ClickUp — Presupuestos + generalidades

> **No es contexto operativo.** No sustituye `docs/context/`.  
> Última actualización: 2026-08-05 (cierre H · avance F Perfil público MVP)  
> Alcance: tareas ClickUp de presupuestos y aspectos generales de la app (no catálogo de productos; no SP salvo outlier anotado).

**Estados de tarea:** `pendiente` | `en_curso` | `hecho` | `hecho · review`  
**Orden de ataque acordado:** A → H → F → C → D → E → G → B → Z

---

## Índice ClickUp → oleada

| ID ClickUp | Título corto | Oleada | Estado |
|------------|--------------|--------|--------|
| 86aht2zpz | Buscador unidades + “otro” | A | **hecho** |
| 86aj0nc3v | Form conceptos: unificar modal → página catálogo | A | **hecho** |
| 86aj3y86n | Totalizar opcional | A | **hecho** |
| 86aj4b9zb | Ocultar total / IVA / importe letra | A | **hecho · review** |
| 86aj541y2 | Párrafo postdata | A | **hecho** |
| 86ahkrr1d | Términos: investigar formas de pago | A | **hecho** (investigación cerrada con A) |
| 86ahha5cu | Corregir y agregar “Más términos” | A | **hecho** |
| 86agutpem | Histórico estados / fechas aceptación-rechazo | A | **hecho** |
| 86aht32jb | Notificación: nº, empresa, título, tipo | A | **hecho** |
| 86aj0n50y | Preview sin preguntar guardar | A | **hecho** |
| 86aj4b61q | Vista preliminar cuadrada | A | **hecho · review** |
| 86aj4b779 | Márgenes PDF / `ppto_config` | A | **hecho** |
| wdx6zev52j | Icono sin borde en app (borde solo impresión) | A | **hecho** |
| wdx6zerea7 | QR pie de página no funciona | A | **hecho** (rutas alineadas; validar `APP_FRONTEND_URL`) |
| wdx6zetawe | Mayúscula automática 1ª letra (nombres propios) | A | **hecho** |
| 86ahxkanc | Imagen en concepto (print vs preview) | A | **hecho** |
| wdx6zev52a | Cerrar sesión + menú de usuario | A | **hecho** (movido desde Z; btn / menú usuario) |
| wdx6zev52b | Usuarios: agregar | H | **hecho** |
| wdx6zev52c | Usuarios: facultades | H | **hecho** |
| 86ah82rb5 | Excluir admin/prueba de estadísticas | H | **hecho** |
| 86af16v4f | Sección compartir información | F | **en_curso** (Perfil público MVP API) |
| 86ahct5vm | Botones compartir (invitar, cédula, bancos, tarjeta) | F | **en_curso** (unificado en Perfil público) |
| 86agv2bbd | Solicitud permiso info bancaria | F | pendiente |
| wdx6zeuxr4 | Ver/corregir datos bancos al compartir | F | pendiente |
| 86ahxk9mz | Evitar doble enlace en invitación | F | pendiente |
| 86ae5jcfj | Invitación DG: validaciones desistibles | F | pendiente |
| 86ah87jnh | Mi empresa: 3ª tarjeta recibe/envía | C | pendiente |
| 86ah5qggq | Mi empresa: quién recibe presupuesto | C | pendiente |
| 86ah5q5u3 | Mi empresa: quién envía presupuesto | C | pendiente |
| 86ahaf9b3 | Dirigido a quién según tarjetas | C | pendiente |
| 86ahcqpap | Cédula fiscal: confirmar reemplazo | D | pendiente |
| 86ahcrf12 | Régimen fiscal: elegir de cédula + default | D | pendiente |
| 86ahdjumu | Número americano fiscal | D | pendiente |
| 86ah5q5j8 | Apellido paterno y materno | D | pendiente |
| 86aj4b1nu | Editar imagen perfil sin recargar | D | pendiente |
| 86ahj0t1k | Detallar catálogo Empresas y clientes | E | pendiente |
| 86agv28yu | Empresas registradas visibles para todos | E | pendiente |
| 86ahdc9xj | Opt-in listado red de proveedores | E | pendiente |
| 86ah6ygvb | Seed ≥30 empresas Construc | E | pendiente (outlier datos/SP) |
| 86ahcnymh | Borrar aceptados → bandeja eliminados | G | pendiente |
| 86ahcnyxn | Marcar pagado + comprobante | G | pendiente |
| wdx6zeunk3 | Ventana en blanco al actualizar | B | pendiente (avance: splash boot PWA en 2.5.2) |
| 86aj1hjwx | Ajuste tamaño de ventanas | B | pendiente |
| 86agj0a6a | Socialite | Z | pendiente |
| 86agz21gv | Guardar usuario/contraseña iPhone | Z | pendiente |
| 86ahcrgfw | PayPal y Stripe | Z | pendiente |

**URLs ClickUp:** `https://app.clickup.com/t/90131590108/{id}`

---

## Oleada A — **COMPLETADA** (2026-08-03)

### Hechos (ciclo A)
- A1–A2 unidades + Otro; control imagen catálogo = Reajustar/Cambiar/Quitar.
- A3 totalizar opcional; A4 ocultar total/IVA/letra (**review**); A5 postdata; A6 formas de pago (investigación cerrada).
- A7 Más términos; A8 historial estados (sheet + logs); A9 notificaciones (títulos oración, mensajes cortos, logo actor, panel campana, API unread-default).
- A10 preview sin preguntar guardar; A11 vista preliminar (**review**); A12 `ppto_config`; A13 iconos; A14 QR; A15 mayúsculas; imagen concepto.
- UX: **Solicitar aprobación** solo emisor.
- **Cerrar sesión + menú de usuario** (`wdx6zev52a`): botón/flujo de logout y menú de usuario alineados a la app actual (incluido en cierre de A; originaba en Z).

### Siguiente
**Oleada F** — compartir información (Perfil público)  
Restos F: permiso bancario, corrección bancos al compartir, evitar doble enlace.

---

## Oleada H — **COMPLETADA** (2026-08-05)

| ID | Título | Estado |
|----|--------|--------|
| wdx6zev52b | Usuarios: agregar | **hecho** |
| wdx6zev52c | Usuarios: facultades | **hecho** |
| 86ah82rb5 | Excluir admin/prueba de estadísticas | **hecho** (`es_cuenta_de_pruebas`) |

---

## Oleada F — en curso (Perfil público MVP)

| ID | Título | Estado |
|----|--------|--------|
| 86af16v4f | Sección compartir información | **en_curso** (API MVP) |
| 86ahct5vm | Botones compartir | **en_curso** (unificado en perfil público) |
| 86agv2bbd | Solicitud permiso info bancaria | pendiente |
| wdx6zeuxr4 | Ver/corregir datos bancos al compartir | pendiente |
| 86ahxk9mz | Evitar doble enlace en invitación | pendiente |
| 86ae5jcfj | Invitación DG: validaciones desistibles | pendiente |

---

## Oleadas (orden)

~~A~~ → ~~H~~ → **F** → C → D → E → G → B → Z

---

## Cómo actualizar

1. Al terminar una tarea ClickUp: poner **hecho** (o **hecho · review**) en la tabla índice.
2. No mover ítems entre oleadas sin acordar el orden.
3. Tareas del día pueden ir en `.TODO` con enlace a este mapa.
