# Usuarios, roles y accesos (plataforma / core)

**No es un dominio de negocio** (no catálogo, no SP, no presupuestos). Forma parte del **core de plataforma**, junto con auth y `proveedor_id`.

Documento de referencia del **MVP de permisos fijos por rol** (sin facultades por usuario).

## Alcance del módulo de gestión

| Área | Qué cubre en MVP |
|------|------------------|
| **Usuarios** | Alta, consulta, edición, baja (según rol) dentro de un proveedor |
| **Roles** | Solo **asignación** de plantillas fijas: `GERENTE`, `SUPERVISOR`, `VENTAS`, `AUXILIAR` |
| **Empresas** | Contexto “mi empresa”; reasignación entre empresas = **solo admin** |

## Usuario principal

- Solo puede existir **un** vínculo `PRINCIPAL` activo por proveedor.
- En MVP: principal = rol **`GERENTE`** + pivot **`PRINCIPAL`**.
- Acceso completo a la empresa (sin restricciones de la matriz).
- **No** se puede borrar desde la gestión de usuarios de la empresa.
- Un **ADMINISTRADOR** sí puede borrar / reasignar / cambiar ese vínculo (panel admin).

Desde la gestión de empresa (gerente/supervisor):

- Solo se crean usuarios **`SECUNDARIO`** (el vínculo `PRINCIPAL` lo gestiona admin).
- Se pueden asignar roles **`GERENTE` | `SUPERVISOR` | `VENTAS` | `AUXILIAR`** (nunca `ADMINISTRADOR`).
- Un usuario con rol `GERENTE` secundario tiene el menú/accesos de gerente; la gestión de usuarios (CRU/delete) sigue reservada al **GERENTE principal**.

## Quién gestiona usuarios

| Acción | GERENTE (principal) | SUPERVISOR | VENTAS / AUXILIAR | ADMIN |
|--------|---------------------|------------|-------------------|-------|
| Ver menú / listar / detalle | T | T | — | T |
| Crear / editar | T | T | — | T |
| Borrar (no principal) | T | — | — | T |
| Borrar principal | — | — | — | T |
| Reasignar a otra empresa | — | — | — | T |

## Matriz de acceso por módulo (MVP)

Letras: **T** = todo · **R** = solo consultar · **—** = sin acceso.

### General

| Capacidad | GERENTE | SUPERVISOR | VENTAS | AUXILIAR |
|-----------|---------|------------|--------|----------|
| Inicio (dashboard) | T | T | T | T |
| Mi Empresa | T | T | — | — |

### Gestión de usuarios

| Capacidad | GERENTE | SUPERVISOR | VENTAS | AUXILIAR |
|-----------|---------|------------|--------|----------|
| Usuarios C · R · U | T | T | — | — |
| Usuarios D | T* | — | — | — |

\* Excepto el principal.

### Presupuestos (dominio; el menú/API respetan esta matriz)

| Capacidad | GERENTE | SUPERVISOR | VENTAS | AUXILIAR |
|-----------|---------|------------|--------|----------|
| Presupuestos | T | T | T | R |
| Clientes | T | T | T | R |
| Catálogo de conceptos | T | T | T | R |
| Tarjetas de presentación | T | T | T | R |

### Solicitudes de pago (SPP)

| Capacidad | GERENTE | SUPERVISOR | VENTAS | AUXILIAR |
|-----------|---------|------------|--------|----------|
| Módulo SPP | T | T | — | — |

### Catálogo de productos (aún no redefinido en esta ronda)

Sigue visible solo para **GERENTE** en menú hasta nueva definición.

## Enforcement (MVP)

1. **Front menú** (`user-menu-new.ts`): `roles: [...]` por ítem según esta matriz.
2. **API usuarios** (`ProveedorUsuarioController` + requests): acceso CRU gerente/supervisor; delete solo gerente (o admin); whitelist de `role_id`; fuerza `SECUNDARIO`.
3. **Dominios (presupuestos / SP):** menú alineado; **por ahora** las rutas `/proveedores/...` aceptan `GERENTE|SUPERVISOR|VENTAS|AUXILIAR` con acceso completo (`config/proveedor_gestion_mvp.php` → `roles_acceso_rutas_proveedor`). Front (`ROLES_PROVEEDOR_OPS` / `user-menu-new.ts`) muestra la **misma vista** a roles operativos que a GERENTE. Pendiente: endurecer endpoints y menú por rol (lectura AUXILIAR, SPP solo G/S, catálogo solo G, etc.).
4. **No hay** UI de facultades por usuario en esta versión.

## Config / código de referencia

| Pieza | Ubicación |
|-------|-----------|
| Config MVP | `config/proveedor_gestion_mvp.php` |
| Enum roles | `App\Enums\UserRoleEnumerate` |
| Controller usuarios | `App\Http\Controllers\ProveedorUsuarioController` |
| Menú front | `app-proveedores/.../pages/user-menu-new.ts` |
| Roles en forms | filtro a GERENTE / SUP / VEN / AUX |

## Qué no hacer

- Inventar facultades por usuario sin actualizar este doc.
- Permitir que el gerente asigne `ADMINISTRADOR` desde la app empresa.
- Promover a vínculo `PRINCIPAL` desde la gestión de empresa (solo admin).
- Mezclar esta lógica dentro de `docs/context/catalogo|solicitudes-pago|presupuestos` — aquí es plataforma.

## Métricas y cuentas de pruebas

Relacionado con KPIs de plataforma (ver también [platform-shared.md](./platform-shared.md#métricas-de-plataforma-operativo)).

### Listado admin de usuarios registrados

En el panel administrativo, el listado de usuarios **solo incluye** roles de producto:

`GERENTE` · `SUPERVISOR` · `VENTAS` · `AUXILIAR` · `CLIENTE`

No aparecen ahí `ADMINISTRADOR`, `CONSTRUCC_APP`, `ventas_purificadora_colibri` ni otros roles de plataforma/integración. Los conteos del listado (todos / activos / …) usan el mismo universo.

**Orden en listados admin:** helper `App\Support\AdminListOrdering`. Usuarios con cuenta bloqueada/suspendida/inactiva van al final. En el listado de usuarios **vinculados a una empresa** (`ProveedorUsuarioController@index`) el orden usa la relación `$proveedor->users()` (pivot `user_proveedor.activo` + `users.status`); vínculos inactivos y cuentas restringidas al final.

### Listado admin de empresas

Por defecto el listado admin de empresas muestra **solo productivas** (`es_cuenta_de_pruebas = false`). Desde filtros avanzados se puede ver «solo pruebas» o «todas».
La marca de pruebas se gestiona en la **edición de la empresa** (panel admin).
En UI admin, las empresas de pruebas llevan el distintivo **DEV** (badge ámbar, mismo patrón visual que Plus; tooltip «Empresa de pruebas») en listado, ficha, usuarios vinculados y reasignación.

### Criterio operativo (métricas)

| Exclusión | Criterio |
|-----------|----------|
| Roles internos | `ADMINISTRADOR`, `CONSTRUCC_APP`, `ventas_purificadora_colibri` — siempre fuera de totales/actividad de producto |
| Usuario de pruebas | `users.es_cuenta_de_pruebas = true` — aplica a **cualquier** rol (p. ej. GERENTE de QA) |
| Empresa de pruebas | `proveedores.es_cuenta_de_pruebas = true` — empresa fuera de totales; actividad con ese `proveedor_id` fuera; usuarios vinculados a ella fuera de totales de usuarios |

Un registro/actividad cuenta solo si **no** cae en ninguna de las tres.

### Alcance

- Afecta métricas de plataforma (dashboard admin, Looker, Pulse de usuarios/empresas).
- **No** borra datos ni restringe login: solo el universo de conteo/reportes de producto.
- El flag puede usarse después para filtros admin / reportes; el nombre describe “pruebas”, no “métricas”.
**Quién marca el flag de pruebas:** solo gestión de plataforma, **en la ficha/edición de la empresa** (`es_cuenta_de_pruebas` del proveedor). El gerente de empresa no lo asigna desde “Mi Empresa”.

### Qué no documentar aquí

Listas de archivos PHP, SQL o nombres de scopes: eso es implementación (`config/metricas_plataforma.php` + modelos). Este doc fija **quién cuenta** como producto.

## Identificador de acceso (correo o teléfono)

Login y registro aceptan **correo** o **teléfono** (campo `email` del login busca también `users.telefono`).

| Origen | Persistencia típica |
|--------|---------------------|
| Registro completo (`registro-proveedor`) | `email` + `telefono` + `telefono_codigo_pais` |
| Registro básico SP (enlace) | `users.email` = teléfono 10 dígitos (legacy); login por ese número |
| Panel admin — usuario (`admin/usuarios`) | Correo **y/o** teléfono con código país (`+52`, etc.) |

**Normalización API:** `App\Support\UserContactData` + requests `UserStoreRequest` / `UserUpdateRequest` (trait `NormalizesUserContactInput`).

- Al menos uno: correo válido **o** teléfono 6–15 dígitos.
- Solo teléfono → `email` = dígitos (compat. login legacy) + `telefono` + `telefono_codigo_pais`.
- Correo + teléfono → ambos campos.

**Front admin:** `panel-administrativo/.../usuario-form` — `app-input-email` + `app-input-phone-country` (mismo patrón que registro). Helper: `usuario-contact.helper.ts`.

## Relación con dominios

Ver [cross-domain.md](./cross-domain.md): auth, roles y gestión de usuarios son **plataforma → cualquiera**; no crean puente presupuesto↔SP.
