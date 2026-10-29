# Multiempresa — una cuenta, varias empresas

Una cuenta (`users`) puede operar en varias empresas (`proveedores`) mediante `user_proveedor`. La sesión tiene **una empresa activa**. Gestión y Nex usan la misma app y la misma API; el header `X-Client-App` no cambia al moverse de empresa.

## Datos

| Campo | Dónde | Uso |
|-------|--------|-----|
| `user_proveedor.role_id` | Membresía | Rol operativo en esa empresa (GERENTE, SUPERVISOR, VENTAS, AUXILIAR). |
| `users.role_id` | Cuenta | Administrador de plataforma y respaldo si la membresía aún no tiene rol. |
| `users.ultima_empresa_id` | Cuenta | Última empresa elegida. Login, `/auth/me` y OAuth la respetan si la membresía sigue activa. |

Alta de la primera empresa: el rol se guarda en la cuenta y en la membresía. Un cambio de rol con **varias** empresas activas solo escribe `user_proveedor.role_id`.

## Reglas

El **gerente** es quien gestiona la empresa: es el rol con poder operativo sobre esa membresía. Supervisor, ventas y auxiliar trabajan en la empresa sin ese mando.

Una cuenta con **más de una empresa** solo se arma desde el administrador (`POST .../users/vincular`, con `role_id` en la membresía). El registro público no agrega una segunda empresa: si el correo o el teléfono ya tienen cuenta completa, `POST /auth/register_proveedor` responde `409` `asignacion_solo_admin`.

## Empresa activa

`App\Services\Auth\EmpresaActivaService`:

1. `ultima_empresa_id` si esa membresía está activa.
2. Si no, la relación `PRINCIPAL`.
3. Si no, la primera membresía activa.

`POST /auth/empresa-activa` con `{ proveedor_id }` valida la membresía, guarda `ultima_empresa_id` y responde `user` (rol de esa empresa), `proveedor` y `empresas`. El token no cambia.

Login, `/auth/me`, refresh, contraseña, perfil, foto y el payload de Google incluyen `empresas`.

## Autorización

En rutas `proveedores/{proveedor}`, `RoleMiddleware` y `ValidateProveedorRole` leen el rol de esa membresía. El middleware `proveedor.access` sigue aceptando principal y secundario.

Logo, constancia fiscal y órdenes de compra autorizan con `tieneAccesoAProveedor` sobre el `{proveedor}` de la ruta. El folio sin id en la URL usa la empresa activa.

`grantClientApp('nexprov')` marca `is_proveedor_catalogo` en la empresa activa, no solo en la principal.

## Front

`empresas` vive en el usuario de sesión. Con dos o más, el sidebar abre un modal (`app-empresa-switcher`) con los datos básicos de cada empresa. Al elegir una llama a `POST /auth/empresa-activa`, limpia la validación de perfil y abre el dashboard del rol de esa empresa.

## Notificaciones

Con la app en primer plano, bandeja, badge, banner y sonido muestran la empresa activa y los avisos sin `proveedor_id` (cuenta, contraseña). `GET /notifications` y “marcar todas” aceptan `proveedor_id`.

Al tocar el push del sistema (app cerrada o en segundo plano) se cambia a la empresa del aviso y después se abre el detalle. Si no había ventana, el service worker abre la URL con `cambiar_empresa`.

## Qué no cambia

El administrador de plataforma no usa el selector: sigue en el panel admin. El acceso a la app (`user_client_apps`) sigue siendo de la cuenta, no de la empresa.
