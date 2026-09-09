# Handoff: Cuentas bancarias y Datos fiscales

Contexto para otra IA — `api-proveedores` + `app-proveedores`.

Fecha de referencia: 2026-07-13  
API: `c:\repositorio\app\api-proveedores` (Laravel)  
Frontend: `c:\repositorio\app\app-proveedores` (Angular + Ionic)

## Glosario

| Término en UI / negocio | Modelo real | Notas |
|-------------------------|-------------|--------|
| "Empresa" del perfil del proveedor | `Proveedor` | No hay entidad `Empresa` de perfil |
| Empresa constructora | `EmpresaConstrucc` | Otro dominio; fiscales mínimos |
| Cuentas bancarias "de empresa" | `CuentaBancaria` con `proveedor_id` | Pertenecen al **Proveedor**, no a EmpresaConstrucc |
| Datos fiscales "de empresa" | Columnas en `proveedores` | Embebidos; no hay tabla `datos_fiscales` |

Formato API: `ApiResponse` (`status`, `code`, `message`, `data`, `errors`).  
Rutas segmentadas por rol en `routes/segmented/`.  
Prefijo típico gerente: `/api/proveedores/{proveedor}/...`.  
Acceso: middleware `proveedor.access` (ownership).  
Respuestas/UI en español.

---

# A. CUENTAS BANCARIAS — implementación actual

## A.1 Modelo de datos

**Tabla:** `cuentas_bancarias`  
**Model:** `app/Models/CuentaBancaria.php` (extends `BaseModel`)  
**Enum:** `app/Enums/EstadoCuentaBancaria.php`  
Valores: `activa`, `inactiva`, `validada`, `pendiente`, `bloqueada`, `eliminada`

### Campos fillable

- `proveedor_id`
- `alias`
- `banco_clave`, `banco_nombre`
- `cuenta`, `clabe`, `tarjeta` (tres columnas; al menos una en store)
- `titular_cuenta`, `referencia`, `sucursal`, `swift`
- `preferida` (bool)
- `estatus` (cast a enum)

### Relaciones

```
Proveedor 1 ── N CuentaBancaria
CuentaBancaria 1 ── N SolicitudPagoCuentaBancaria  (snapshot en solicitudes de pago)
```

- `Proveedor::cuentasBancarias()`
- `CuentaBancaria::proveedor()`
- Snapshot: `app/Models/SolicitudPagoCuentaBancaria.php` / tabla `solicitud_pago_cuentas_bancarias`

### Migraciones clave

- `database/migrations/2025_08_30_013617_create_table_cuentas_bancarias.php`
- `database/migrations/2025_10_27_182064_update_table_cuentas_bancarias_add_estatus.php`
- `database/migrations/2026_03_19_120000_refactor_cuentas_bancarias_cuenta_clabe_tarjeta.php`  
  (refactor: de `tipo_cuenta` + `campo_dependiente` a columnas `cuenta`/`clabe`/`tarjeta`)

### Fuera de este dominio

- `cuenta_bancaria_empresa_construcc_id` en `SolicitudPago` / pagos: **ID externo** de constructora, no FK a `cuentas_bancarias` locales.

## A.2 API

### Gerente

**Rutas:** `routes/segmented/gerente.php`  
**Controller:** `app/Http/Controllers/ProveedorCuentaBancariaController.php`  
**Prefijo:** `/api/proveedores/{proveedor}/cuentas-bancarias`

| Método | Ruta | Acción |
|--------|------|--------|
| GET | `/` | index (filtros + paginación) |
| POST | `/` | store |
| GET | `/{cuenta}` | show |
| PATCH | `/{cuenta}` | update |
| DELETE | `/{cuenta}` | destroy |
| GET | `/preferida` | getPreferida |
| POST | `/preferida` | setPreferida (`cuenta_ids[]`) |

Middleware: `auth:sanctum`, `role:GERENTE`, `proveedor.access`; en `{cuenta}` también `proveedor.cuenta`, `api.access`.

**Requests:**

- `app/Http/Requests/CuentaBancaria/CuentaBancariaStoreRequest.php`
- `app/Http/Requests/CuentaBancaria/UpdateCuentaBancariaRequest.php`

**Resource:** `app/Http/Resources/CuentaBancaria/CuentaBancariaResource.php`  
(Nota: **no expone `estatus`** en el resource.)

**Reglas compartidas (parcialmente relevantes):** `app/Rules/FiscalBancarioRules.php`  
(`clabe`, `tarjeta`, `cuenta`, `cuentaBancaria()` — este último aún referencia schema legacy `tipo_cuenta` / `campo_dependiente` en parte del código de reglas).

### Construcc (API key)

**Rutas:** `routes/segmented/construcc.php`  
**Controller:** `ConstruccProveedorCuentaBancariaController.php`  
**Prefijo:** `/api/construcc/proveedor/{proveedor}/cuentas`  
Restricción: proveedores con `tipo_alta = 2`.

## A.3 Frontend

Base: `app-proveedores/src/app/pages/proveedor/perfil-usuario-proveedor/`

| Pieza | Path |
|-------|------|
| Model | `model/proveedor-cuenta-bancaria.ts` → `ICuentaBancaria` |
| Servicio | `services/proveedor-cuenta-bancaria.service.ts` → CRUD + preferida |
| UI | `components/datos-bancarios-form/` |
| Validators | `components/datos-bancarios-form/validators/bank.validators.ts` |
| Página | tab `bancarios` en perfil; requisito de perfil completo |
| Uso secundario | selección de cuentas al crear SP en `solicitud-pago-proveedor` |

**Gap front/back:** el servicio expone `reorderCuentas` → `.../cuentas-bancarias/reorder`; **esa ruta no existe en la API**.

## A.4 Defectos / deuda conocidos

1. Middleware `EnsureProveedorCuentaBancariaAccess` busca `route('cuenta_bancaria')`, pero la ruta usa `{cuenta}` → la validación de pertenencia del middleware puede **no ejecutarse**; update valida `proveedor_id` a mano; show/destroy gerente pueden quedar débiles.
2. `getPreferida` filtra `estatus` de forma inconsistente (valor numérico vs enum string).
3. `Proveedor::scopeCuentasActivas` usa `'activo'` vs enum `'activa'`.
4. Resource sin `estatus`.
5. Endpoint `reorder` ausente.
6. Conceptualmente no son "cuentas de EmpresaConstrucc"; el naming de negocio puede confundir.

## A.5 Veredicto del módulo cuentas

Patrón **correcto** según reglas del proyecto: recurso hijo 1:N, CRUD anidado, Request + Resource, filtros en modelo.  
Hay bugs de naming/enum/middleware y un endpoint fantasma en front.

---

# B. DATOS FISCALES — implementación actual

## B.1 Modelo de datos

**No existe** modelo/tabla `DatosFiscales`.  
Los datos viven **embebidos en `proveedores`**.

**Model:** `app/Models/Proveedor.php`

### Campos fiscales relevantes

| Campo | Rol |
|-------|-----|
| `razon_social` | Razón social |
| `rfc` | RFC (históricamente unique; nullable en evolución) |
| `tipo_persona` | Física / Moral |
| `regimen_fiscal_clave`, `regimen_fiscal_nombre` | Un solo régimen |
| `constancia_fiscal` | Path del PDF (disk `public`) |
| `calle`, `numero_exterior`, `numero_interior`, `colonia`, `ciudad`, `estado`, `codigo_postal`, `pais` | Domicilio plano |
| `direccion_fiscal` | Columna **string legacy**; poco usada al guardar el form actual |

**Resource:** `app/Http/Resources/ProveedorResource.php`  
Arma objeto `direccion_fiscal` desde columnas planas (no lee JSON de BD).

### Migraciones fiscales

- `database/migrations/2025_03_01_020132_create_proveedores_table.php`
- `database/migrations/2025_08_29_201748_add_fiscal_fields_to_proveedores_table.php`
- `database/migrations/2025_09_01_194037_adjust_proveedor_fiscal_fields.php`

### Otros dominios relacionados (no son el perfil fiscal del proveedor)

| Pieza | Qué es |
|-------|--------|
| `EmpresaConstrucc` | `rfc`, `razon_social`, `direccion`, `ciudad`, `estado`, `codigo_postal` — fiscales ligeros de constructora |
| `SolicitudPago` | `datos_facturacion_id`, `razon_social_id`, uso/método/forma pago — receptor vía **Inter API** (`InterApiService`), no tabla local del emisor |

## B.2 API

Actualización fiscal = parte de actualizar el proveedor.

| Método | Ruta | Uso |
|--------|------|-----|
| GET | `/proveedores/{proveedor}` | Incluye bloque fiscal en resource |
| PATCH | `/proveedores/{proveedor}` | Actualiza fiscales entre otros campos |
| POST | `/proveedores/{proveedor}/constancia-fiscal` | Sube PDF + extracción; **no persiste** solo por sí misma los campos fiscales |
| GET | `.../constancia-fiscal/preview` | Preview PDF |
| GET | `.../constancia-fiscal/download` | Ruteado; **método ausente** en controller (deuda) |
| POST | `.../verificar-rfc-excluyendo-proveedor` | Unicidad RFC |
| POST | `.../verificar-razon-social-excluyendo-proveedor` | Unicidad razón social |
| GET | `/proveedores/{proveedor}/perfil-completado` | Flags generales/fiscales/bancarios (`shared.php`) |
| GET | `/proveedores/{proveedor}/puede-generar-sp` | Incluye check de fiscales |

**Controller:** `app/Http/Controllers/ProveedorController.php`  
**Update request:** `app/Http/Requests/Proveedor/ProveedorUpdateRequest.php` (campos fiscales con `sometimes`)  
**Constancia request:** `app/Http/Requests/Proveedor/ProveedorUpdateConstanciaFiscalRequest.php`  
**Extracción PDF:** `app/Services/Proveedor/ConstanciaFiscalHybridService.php`

**Reglas definidas pero no cableadas al request fiscal de update:**  
`App\Rules\FiscalBancarioRules::datosFiscales()` — RFC, régimen, domicilio completo, etc.

**Catálogo de regímenes SAT:** solo en frontend (`regimenesFiscales.ts`); **no hay endpoint de catálogo** en API.

**Completitud API vs front:**  
API (p. ej. validaciones para SP) suele exigir menos (típicamente `razon_social` + `rfc`).  
Front exige más (dirección, régimen). → desalineación.

## B.3 Frontend

| Pieza | Path |
|-------|------|
| Página perfil (segmento fiscales) | `.../perfil-usuario-proveedor/pages/perfil-usuario-proveedor/` |
| Formulario | `.../components/datos-fiscales-form/` |
| Constancia PDF | `.../components/constancia-fiscal-form/` |
| Bus extracción | `.../services/datos-fiscales-shared.service.ts` |
| Model TS | `src/app/@core/data/proveedor.model.ts` (`DireccionFiscal`, `IProveedor`) |
| HTTP | `src/app/@core/mock/proveedor.service.ts` |
| Catálogo regímenes | `.../catalogs/regimenesFiscales.ts` |
| Validators | `.../datos-fiscales-form/validators/` |

Flujo guardar: form → `proveedorService.update(id, form.value)` con **campos planos** (`rfc`, `calle`, …), alineado al Form Request.

Flujo constancia: upload → extracción → evento compartido → usuario confirma en form → PATCH.

## B.4 Limitaciones actuales

1. Todo embebido en `proveedores` → sin multi-razón, multi-régimen ni historial limpio.
2. Ambigüedad `direccion_fiscal` string (BD) vs objeto (Resource) vs columnas `calle`….
3. `ciudad` / `estado` / `codigo_postal` pueden mezclar uso operativo y fiscal (mismo fillable).
4. Completitud front ≠ API.
5. `FiscalBancarioRules::datosFiscales()` no usada en el update real.
6. Un solo régimen; constancia puede traer varios y se colapsa.
7. Rutas/métodos incompletos (`download`; posibles rutas front sin backend).
8. Facturación SP acoplada a Inter API (otro bounded context).
9. Catálogo SAT duplicado solo en Angular.

## B.5 Veredicto del módulo fiscales

Cumple caso de uso **un emisor por proveedor**.  
**No** sigue el patrón API de recurso hijo (a diferencia de cuentas bancarias). Escala mal si se necesita multi-RFC, historial o catálogos oficiales en backend.

---

# C. DISEÑO CORREGIDO (si se reimplementa / otra API)

Objetivo: mismo molde que cuentas bancarias; contratos claros para una IA implementadora.

## C.1 Cuentas — correcciones a aplicar

1. Entidad 1:N anidada bajo empresa/proveedor (mantener).
2. Unificar enum `estatus` string en BD, filtros, scopes, Resource.
3. Alinear route param `{cuenta}` con el middleware de pertenencia.
4. Exponer `estatus` en Resource.
5. Implementar `reorder` o eliminarlo del cliente.
6. Preferida: máximo una activa; operación atómica.
7. Snapshots de pago separados; IDs externos de constructora fuera de esta tabla.
8. Reglas de formato CLABE/cuenta/tarjeta centralizadas y usadas por Requests actuales (no schema legacy).

### Contrato sugerido

```
GET/POST     /{root}/{id}/cuentas-bancarias
GET/PATCH/DELETE /{root}/{id}/cuentas-bancarias/{cuentaId}
GET/POST     /{root}/{id}/cuentas-bancarias/preferida
```

## C.2 Fiscales — diseño adecuado y escalable

### Modelo

```
Root (Proveedor/Empresa) 1 ── 1 DatosFiscales   # arranque
                         1 ── N DatosFiscales   # si multi-emisor / historial
```

Tabla `datos_fiscales`: identidad + régimen(es) + domicilio fiscal propio + constancia.  
Opcional: `datos_fiscales_regimenes` para N regímenes.  
Catálogo `regimenes_fiscales` en backend.

### Contrato sugerido

```
GET/PUT  /{root}/{id}/datos-fiscales
POST     /{root}/{id}/datos-fiscales/constancia
GET      /{root}/{id}/datos-fiscales/constancia/preview
GET      /catalogos/regimenes-fiscales
GET      /{root}/{id}/perfil-completado
```

### Reglas

- Misma completitud en API y front (razón social, RFC, régimen, domicilio mínimo).
- Cablear `FiscalBancarioRules::datosFiscales()` (o equivalente) en el Request.
- Domicilio fiscal ≠ dirección operativa.
- Una sola representación de dirección (sin string legacy dual).
- Constancia: upload + extracción; persistencia de campos vía PUT confirmado o flag explícito en transacción.
- Separar: perfil fiscal del root ≠ fiscales ligeros de constructora ≠ CFDI del documento (SP).

### Cuándo 1:1 vs 1:N

- **1:1:** un solo emisor por empresa (caso actual del producto).
- **1:N:** multi-RFC, historial, o varios regímenes/emisores vigentes.

## C.3 Piezas transversales

- AuthZ por `id` del path (recurso hijo pertenece al root).
- Form Request + Resource por módulo.
- Filtros en modelo + paginación en listados.
- Sin endpoints fantasma.
- Migraciones versionadas; sin columnas duplicadas legacy desde el inicio si es API nueva.

---

# D. MAPA RÁPIDO DE ARCHIVOS

## API — cuentas

- `app/Models/CuentaBancaria.php`
- `app/Enums/EstadoCuentaBancaria.php`
- `app/Http/Controllers/ProveedorCuentaBancariaController.php`
- `app/Http/Controllers/ConstruccProveedorCuentaBancariaController.php` (si aplica)
- `app/Http/Requests/CuentaBancaria/*`
- `app/Http/Resources/CuentaBancaria/CuentaBancariaResource.php`
- `routes/segmented/gerente.php`
- `routes/segmented/construcc.php`

## API — fiscales

- `app/Models/Proveedor.php`
- `app/Http/Controllers/ProveedorController.php`
- `app/Http/Requests/Proveedor/ProveedorUpdateRequest.php`
- `app/Http/Requests/Proveedor/ProveedorUpdateConstanciaFiscalRequest.php`
- `app/Http/Resources/ProveedorResource.php`
- `app/Services/Proveedor/ConstanciaFiscalHybridService.php`
- `app/Rules/FiscalBancarioRules.php`
- `app/Models/EmpresaConstrucc.php` (relacionado, no perfil proveedor)
- `routes/segmented/gerente.php`, `routes/segmented/shared.php`

## App — ambos

- Perfil: `src/app/pages/proveedor/perfil-usuario-proveedor/`
- Cuentas: `components/datos-bancarios-form/`, `services/proveedor-cuenta-bancaria.service.ts`
- Fiscales: `components/datos-fiscales-form/`, `components/constancia-fiscal-form/`
- Core proveedor: `src/app/@core/data/proveedor.model.ts`, `src/app/@core/mock/proveedor.service.ts`

---

# E. INSTRUCCIONES PARA OTRA IA

Si debes **entender el sistema actual:**

- Cuentas = CRUD hijo real sobre `CuentaBancaria`.
- Fiscales = campos del `Proveedor` + constancia como archivo; no entidad separada.

Si debes **replicar en otra API:**

- Cuentas: copiar patrón 1:N corrigiendo enum/middleware/resource/reorder.
- Fiscales: **no copiar el embebido**; crear recurso `datos-fiscales` 1:1 (o 1:N), catálogo SAT en backend, completitud server-side, separar facturación documental.

Si debes **arreglar en este repo:**

- Priorizar: pertenencia `{cuenta}`, enum `estatus`, cablear reglas fiscales, alinear completitud API/front, eliminar rutas muertas, decidir fate de `direccion_fiscal` legacy.

**Regla de oro del proyecto:** lo anidado bajo `proveedores/{id}/recurso` con Request + Resource + ownership es el patrón canónico; fiscales hoy es la excepción a corregir.
