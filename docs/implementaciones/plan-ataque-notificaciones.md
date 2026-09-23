# Plan de ataque — Notificaciones (Reverb + FCM)

Objetivo: dejar el sistema **consistente y documentado** sin romper lo que ya funciona.

Fuera de alcance: orden de compra (nunca implementado).

Documento de contexto: [notificaciones-reverb-firebase.md](./notificaciones-reverb-firebase.md)

**Hecho (multi-app):** segmentación FCM por `app_key` — ver [notificaciones-segmentacion-apps.md](./notificaciones-segmentacion-apps.md).

---

## Estado actual (recordatorio)

| Capa | Qué hay | Qué duele |
|------|---------|-----------|
| Reverb + Echo | Canal privado por usuario, funciona | Canales legacy en front, docs viejas |
| FCM | `FcmService` + tokens, funciona | `toFcm()` a veces void (side-effect) |
| Bandeja | Tabla + API REST | OK |
| Ops | Reverb + Sanctum auth | `.env.example` confuso |

---

## Fases

### Fase 0 — Baseline

Sin cambiar código de negocio. Hacer primero al retomar.

- [ ] Listar notificaciones **activas** (SP, presupuesto, cotización, auth, asociación, `PushNotification`)
- [ ] Anotar 1 usuario de prueba + 1 flujo por tipo (ej. SP pagada, presupuesto enviado, `POST /notifications/test`)
- [ ] Checklist de no-regresión:
  - Echo conecta post-login
  - Campana + banner
  - Fila en `notifications`
  - FCM en background
  - Logout desactiva token
- [ ] Capturar logs actuales: ¿aparece “Payload inválido” en FCM Channel?

**Done cuando:** hay checklist y 2–3 disparos de prueba documentados.

---

### Fase 1 — Docs / env (bajo riesgo)

- [ ] Alinear `.env.example` con `BROADCAST_CONNECTION=reverb` + `REVERB_*` + FCM
- [ ] Marcar `BROADCAST_DRIVER` como obsoleto
- [ ] Actualizar README front si habla de Socket.IO / WebSockets viejos
- [ ] En el MD de implementación: nota “OC fuera de alcance / no implementar”

**Done cuando:** un compañero puede montar Reverb + FCM solo con docs.

**PR sugerido:** `docs: clarificar env Reverb y FCM`

---

### Fase 2 — Unificar `toFcm()` (impacto alto, riesgo controlado)

Patrón objetivo (como `PushNotification`):

```php
public function toFcm(object $notifiable): array
{
    return [
        'notification' => ['title' => '...', 'body' => '...'],
        'data' => [ /* strings + correlation_id */ ],
    ];
}
```

- [ ] **2.1** Una notificación piloto (ideal: `PushNotification` ya OK → tomar otra simple, ej. auth)
- [ ] Quitar `app(FcmService)->sendToTokens` del `toFcm`
- [ ] Dejar solo el `return` array; canal `fcm` en `via()`
- [ ] Probar: push + campana + sin log “Payload inválido”
- [ ] **2.2** Resto: SolicitudPago*, Presupuesto*, Cotizacion*, ProveedorEmpresa*, etc.
- [ ] Confirmar que `NotificationCorrelationId` / estilos siguen en `data`

**Done cuando:** todas las notificaciones activas usan `toFcm(): array`; `FcmChannel` es el único envío.

**PR sugeridos:**

1. `fix(fcm): unificar toFcm en [piloto]`
2. `fix(fcm): unificar toFcm en notificaciones de dominio`

---

### Fase 3 — Contratos API / webhooks

- [ ] Revisar `solicitud-pago/pagada` y `/rechazada` vs constructores de Notification
- [ ] Ajustar solo args desalineados (sin cambiar nombre de rutas ni auth ApiKey)
- [ ] Probar ambos webhooks + Echo + FCM + DB

**Done cuando:** webhooks SP disparan sin errores de constructor/args.

**PR sugerido:** `fix(notifications): alinear payload webhooks SP`

---

### Fase 4 — Front: un solo canal

- [ ] Mantener solo `private('App.Models.User.{id}')` + `.notification()`
- [ ] Dejar de suscribir `public-notifications` / canales de recurso **si** no aportan nada hoy
- [ ] Ideal: flag `ENABLE_LEGACY_WS_CHANNELS=false` un sprint, luego quitar código
- [ ] Verificar campana con Reverb caído (poll + FCM siguen)

**Done cuando:** UI igual; menos suscripciones; sin eventos fantasma.

**PR sugerido:** `refactor(ws): suscribir solo canal privado de usuario`

---

### Fase 5 — Código muerto

- [ ] Grep: listeners FCM / events no registrados → eliminar o archivar en docs
- [ ] No tocar migraciones de `notification_types` en prod; solo documentar “unused”
- [ ] No borrar archivos OC en el mismo PR si ensucia el diff; PR aparte `chore: remove dead OC notification code` si se quiere limpio

**Done cuando:** no hay paths que “parecen” activos y no lo están.

---

### Fase 6 — Mejoras opcionales

Solo empezar si las fases 1–5 están cerradas.

| Ítem | Valor | Nota |
|------|-------|------|
| Preferencias mute por tipo | Alto producto | Feature nueva; default “todo activo” = comportamiento actual |
| FCM en cola (`ShouldQueue` solo FCM) | Resiliencia | Requiere `queue:work` siempre |
| Reconexión Echo con backoff | Estabilidad red | Evitar spam de reconnect |
| Kreait | Bajo (no necesario) | Evitar; rewrite innecesario |

---

## Orden de PRs (retomable)

```
0  Baseline + checklist manual
1  Docs env/README
2a toFcm piloto
2b toFcm resto
3  Webhooks SP
4  Echo un canal
5  Dead code
6  (opcional) preferencias / cola / reconnect
```

---

## Al retomar

1. Abrir este plan + [notificaciones-reverb-firebase.md](./notificaciones-reverb-firebase.md)
2. Ver qué fase/PR quedó a medias
3. Correr checklist Fase 0 con usuario de prueba
4. Continuar en la siguiente casilla sin saltar 2 → 4 (unificar FCM antes de limpiar Echo)

---

## Criterio de “tema cerrado”

- [ ] Un solo patrón FCM (`toFcm` + channel)
- [ ] Env/docs coherentes
- [ ] Front en un canal productivo
- [ ] Webhooks SP alineados
- [ ] Sin listeners muertos confusos
- [ ] Checklist de no-regresión en verde

---

## Fuera de alcance permanente (salvo decisión nueva)

- Reimplementar orden de compra
- Migrar a Kreait
- Preferencias de usuario (hasta Fase 6)
- Cambiar nombre del canal `App.Models.User.{id}`
