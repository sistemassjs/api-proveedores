# Solicitudes de cotización NexProv — API para Codex / front

Dominio: **Cotizaciones NexProv** (no legacy Construcc).

- **Usar:** `/api/proveedores/{proveedor}/solicitudes-cotizacion`
- **No usar:** `/proveedores/{proveedor}/cotizaciones` ni tablas `cotizaciones` / `cotizacion_detalles`

## Contrato general

| Item | Valor |
|------|--------|
| Base API | `/api` |
| Auth | Sanctum `Authorization: Bearer {token}` |
| Middleware | roles proveedor + `proveedor.access` |
| Response | ApiResponse `{ status, code, message, data, errors }` |
| Controller | `App\Http\Controllers\Cotizacion\ProveedorSolicitudCotizacionController` |
| Front | Angular + Ionic (`app-proveedores`) |

## Prefijo

```
/api/proveedores/{proveedor}/solicitudes-cotizacion
```

## Estatus

```
recibida → en_revision → respondida → procesada
también: cerrada | rechazada
```

Orígenes: `publico_cotizador` | `nexprov_interna` | `empresa_tercero`.

- `GET /{id}`: si estaba `recibida`, pasa a `en_revision`.
- `POST .../responder`: pasa a `respondida` si hubo canal útil + archiva PDF en galería.
- `POST .../procesar`: pasa a `procesada` + archiva PDF definitivo.

---

## Endpoints

### 1. Inbox

`GET /api/proveedores/{proveedor}/solicitudes-cotizacion`

**Query**

| Param | Default | Notas |
|-------|---------|--------|
| `estatus` | — | CSV (`recibida,en_revision,procesada`) |
| `origen` | — | CSV (`publico_cotizador,nexprov_interna`) |
| `search` | — | folio, nombre, email, teléfono, whatsapp, solicitante_empresa |
| `fecha_desde` | — | por `created_at` |
| `fecha_hasta` | — | por `created_at` |
| `sort_by` | `created_at` | |
| `order` | `desc` | |
| `per_page` | `15` | 5..100 |

**Response:** paginado + meta `estatus_counts` (incluye `procesada` + `todas`).

Incluye `detalles` en el listado.

### 1b. Alta interna

`POST .../`

```json
{
  "origen": "nexprov_interna|empresa_tercero",
  "cliente_nombre": "string",
  "cliente_email": "email",
  "solicitante_empresa": "opcional",
  "vigencia_hasta": "YYYY-MM-DD",
  "politicas": "línea1\\nlínea2",
  "detalles": [{ "producto_id": 1, "cantidad": 2, "precio_unitario": 100 }]
}
```

### 1c. Galería

- `GET .../galeria?tipo=envio,procesada&search=`
- `GET .../galeria/{archivo}/pdf`

---

### 2. Productos para agregar líneas

`GET .../productos`

| Param | Default | Notas |
|-------|---------|--------|
| `q` / `search` | `""` | nombre, codigo_interno, sku |
| `limit` | `20` | 1..50 |
| `solo_publicos` | `false` | filtra `mostrar_en_catalogo_publico` |

**Item:**

```ts
{
  id: number
  nombre: string
  codigo: string | null
  unidad: string | null
  precio_base: number | null
  stock: number | null
  mostrar_en_catalogo_publico: boolean
  imagen_url: string | null
}
```

---

### 3. Detalle

`GET .../{solicitudCotizacion}`

- 403 si no pertenece al proveedor.
- Side-effect: `recibida` → `en_revision`.
- Eager: `proveedor`, `detalles`, `respuestas`.

---

### 4. Actualizar

`PATCH .../{solicitudCotizacion}`

```json
{
  "cliente_nombre": "string",
  "cliente_email": "email",
  "cliente_telefono": "string|null",
  "cliente_whatsapp": "string|null",
  "cliente_notas": "string|null",
  "solicitante_empresa": "string|null",
  "observaciones_internas": "string|null",
  "vigencia_hasta": "YYYY-MM-DD|null",
  "politicas": "string|null",
  "estatus": "recibida|en_revision|respondida|procesada|cerrada|rechazada",
  "detalles": [
    {
      "id": 10,
      "producto_id": 5,
      "nombre": "...",
      "descripcion": "...",
      "unidad": "PZA",
      "codigo": "...",
      "imagen": "...",
      "precio_referencia": 100,
      "precio_unitario": 95,
      "cantidad": 2,
      "es_sugerencia_empresa": false,
      "motivo_sugerencia": null,
      "observaciones_linea": null,
      "orden": 0,
      "eliminar": false
    }
  ]
}
```

**Sync de `detalles`**

- con `id` → update
- sin `id` → create (`producto_id` o línea libre/sugerida)
- `eliminar: true` → delete
- si envías `detalles`: min 1, max 200
- recalcula `total`

---

### 5. Preview PDF

`GET .../{solicitudCotizacion}/pdf`

- Response binaria PDF (no JSON).
- Generado al vuelo (no historial).

---

### 6. Responder

`POST .../{solicitudCotizacion}/responder`

```json
{
  "canales": "email|whatsapp|ambos",
  "mensaje": "opcional"
}
```

**Reglas**

- Requiere ≥1 detalle (422 si no).

**Flujo backend**

1. Genera PDF (disk `private`)
2. Email si aplica (fallo no aborta)
3. WhatsApp: link `wa.me`; API solo si `services.whatsapp.enabled`
4. Persiste `solicitud_cotizacion_respuestas` + archivo galería (`tipo=envio`)

### 6b. Procesar (cliente usó la cotización)

`POST .../{solicitudCotizacion}/procesar`

```json
{ "nota": "opcional", "generar_pdf": true }
```

Estatus → `procesada` + PDF en galería (`tipo=procesada`).

**Response `data`**

```json
{
  "solicitud": { "...SolicitudCotizacion" },
  "respuesta": { "...SolicitudRespuesta" }
}
```

UI: si existe `respuesta.whatsapp_link`, ofrecer abrir/copiar aunque falle la API de WhatsApp.

---

### 7. PDF de respuesta enviada

`GET .../{solicitudCotizacion}/respuestas/{respuesta}/pdf`

- Download del PDF guardado.
- 404 si no pertenece o no existe.
- Filename: `Cotizacion_{folio}.pdf`

---

## Types

```ts
type SolicitudCotizacion = {
  id: number
  folio: string // SC000001
  origen: string // publico_cotizador
  estatus: 'recibida' | 'en_revision' | 'respondida' | 'procesada' | 'cerrada' | 'rechazada'
  origen: 'publico_cotizador' | 'nexprov_interna' | 'empresa_tercero'
  proveedor_id: number
  proveedor?: {
    id: number
    nombre_comercial: string
    razon_social: string
    logo: string | null
  }
  cliente_nombre: string
  cliente_email: string
  cliente_telefono?: string | null
  cliente_whatsapp?: string | null
  cliente_notas?: string | null
  solicitante_empresa?: string | null
  observaciones_internas?: string | null
  vigencia_hasta?: string | null
  politicas?: string | null
  politicas_lista?: string[]
  total: number
  respondida_at?: string | null
  cerrada_at?: string | null
  procesada_at?: string | null
  detalles?: SolicitudDetalle[]
  respuestas?: SolicitudRespuesta[]
  archivos?: SolicitudArchivo[]
  created_at: string
  updated_at: string
}

type SolicitudArchivo = {
  id: number
  tipo: 'envio' | 'procesada' | 'manual'
  nombre: string
  folio?: string
  cliente_nombre?: string
  created_at: string
}

type SolicitudDetalle = {
  id: number
  producto_id: number | null
  nombre: string
  descripcion?: string | null
  unidad?: string | null
  codigo?: string | null
  imagen?: string | null
  precio_referencia?: number | null
  precio_unitario: number
  cantidad: number
  subtotal: number
  es_sugerencia_empresa: boolean
  motivo_sugerencia?: string | null
  observaciones_linea?: string | null
  orden: number
}

type SolicitudRespuesta = {
  id: number
  canales: 'email' | 'whatsapp' | 'ambos'
  mensaje?: string | null
  pdf_path?: string | null
  email_estado?: string | null
  email_error?: string | null
  whatsapp_modo?: string | null
  whatsapp_estado?: string | null
  whatsapp_link?: string | null
  whatsapp_error?: string | null
  payload_resumen?: unknown
  enviado_por?: { id: number; name: string; email: string } | null
  created_at: string
}
```

---

## UI sugerida

- Listado: `/pages/proveedor/cotizaciones/solicitudes`
- Detalle: `/pages/proveedor/cotizaciones/solicitudes/:id`

**Flujo**

1. `GET /` + chips con `estatus_counts`
2. `GET /{id}`
3. `PATCH` líneas/precios (+ `GET /productos` para agregar)
4. `GET /{id}/pdf` preview
5. `POST /{id}/responder`
6. `GET /{id}/respuestas/{respuestaId}/pdf` histórico

---

## Errores

| Code | Cuándo |
|------|--------|
| 403 | Solicitud de otro proveedor |
| 404 | Respuesta/PDF inexistente |
| 422 | Validación / sin líneas al responder |
| 500 | Fallo update/send |

---

## Notas de dominio

- Detalles con snapshot editable (nombre/unidad/precio/imagen).
- La empresa puede agregar sugerencias (`es_sugerencia_empresa`).
- Notificación al crear desde público: solo app `nexprov`.
- Entrada pública (referencia): `POST /api/public/cotizador/empresas/{proveedor}/solicitudes`
- Docs relacionadas: [api.md](./api.md), [front.md](./front.md), [database.md](./database.md), [overview.md](./overview.md)
