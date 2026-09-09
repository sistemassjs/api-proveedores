# Endpoint de Contacto - API Proveedores

## Descripción

Endpoint público para el envío de mensajes desde el formulario de contacto del sitio web. No requiere autenticación.

## Endpoint

```
POST /api/contacto/enviar
```

## Headers

```
Content-Type: application/json
Accept: application/json
```

## Parámetros del Request

| Campo    | Tipo   | Requerido | Máx. Caracteres | Descripción                           |
|----------|--------|-----------|-----------------|---------------------------------------|
| nombre   | string | Sí        | 255             | Nombre completo de quien contacta     |
| email    | string | Sí        | 255             | Correo electrónico válido             |
| telefono | string | No        | 20              | Número de teléfono (opcional)         |
| empresa  | string | No        | 255             | Nombre de la empresa (opcional)       |
| mensaje  | string | Sí        | 2000            | Mensaje o consulta                    |

## Ejemplo de Request

```json
{
  "nombre": "Juan Pérez García",
  "email": "juan.perez@ejemplo.com",
  "telefono": "668-123-4567",
  "empresa": "Constructora ABC S.A. de C.V.",
  "mensaje": "Me gustaría obtener más información sobre cómo registrarme como proveedor en su plataforma. ¿Qué documentos necesito?"
}
```

## Respuestas

### Respuesta Exitosa (200 OK)

```json
{
  "success": true,
  "message": "Mensaje enviado correctamente. Nos pondremos en contacto contigo pronto."
}
```

### Errores de Validación (422 Unprocessable Entity)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "nombre": [
      "El nombre es obligatorio"
    ],
    "email": [
      "Debe proporcionar un correo electrónico válido"
    ],
    "mensaje": [
      "El mensaje es obligatorio"
    ]
  }
}
```

### Error del Servidor (500 Internal Server Error)

```json
{
  "success": false,
  "message": "Hubo un error al enviar el mensaje. Por favor, intenta nuevamente más tarde.",
  "error": "Detalles del error (solo en modo debug)"
}
```

### Rate Limit Excedido (429 Too Many Requests)

```json
{
  "message": "Too Many Attempts."
}
```

## Rate Limiting

- **Límite**: 5 solicitudes por minuto por IP
- **Respuesta**: 429 Too Many Requests cuando se excede el límite

## Configuración

### Variables de Entorno

Agregar en el archivo `.env`:

```env
# Correo de destino para formulario de contacto
MAIL_CONTACT_TO=contacto@sjsconstrucciones.com
```

Si no se configura esta variable, el valor por defecto será `contacto@sjsconstrucciones.com`.

### Configuración de Email

Asegúrate de tener correctamente configurado el servicio de correo en tu archivo `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu_email@gmail.com
MAIL_PASSWORD=tu_password_app
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@sjsconstrucciones.com"
MAIL_FROM_NAME="SJS Construcciones"
```

## Características

1. **Validación robusta**: Todos los campos son validados con mensajes en español
2. **Rate limiting**: Protección contra spam con límite de 5 mensajes por minuto
3. **Reply-To**: El correo que recibes incluye el email del remitente en el campo Reply-To para responder fácilmente
4. **Logging**: Todos los envíos y errores se registran en los logs de Laravel
5. **Template HTML**: El correo se envía con un diseño limpio y profesional
6. **Manejo de errores**: Respuestas claras en caso de error

## Ejemplo de Uso con JavaScript (Fetch API)

```javascript
async function enviarContacto() {
  try {
    const response = await fetch('https://api.sjsconstrucciones.com/api/contacto/enviar', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        nombre: 'Juan Pérez García',
        email: 'juan.perez@ejemplo.com',
        telefono: '668-123-4567',
        empresa: 'Constructora ABC',
        mensaje: '¿Cómo puedo registrarme como proveedor?'
      })
    });

    const data = await response.json();
    
    if (response.ok) {
      console.log('Éxito:', data.message);
      alert(data.message);
    } else {
      console.error('Error:', data);
      alert(data.message || 'Error al enviar el mensaje');
    }
  } catch (error) {
    console.error('Error de red:', error);
    alert('Error de conexión. Por favor, intenta más tarde.');
  }
}
```

## Ejemplo de Uso con cURL

```bash
curl -X POST https://api.sjsconstrucciones.com/api/contacto/enviar \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "nombre": "Juan Pérez García",
    "email": "juan.perez@ejemplo.com",
    "telefono": "668-123-4567",
    "empresa": "Constructora ABC",
    "mensaje": "¿Cómo puedo registrarme como proveedor?"
  }'
```

## Logs

Los envíos exitosos y errores se registran en:

- **Envíos exitosos**: `storage/logs/laravel.log` con nivel INFO
- **Errores**: `storage/logs/laravel.log` con nivel ERROR

Ejemplo de log exitoso:
```
[2024-02-21 10:30:45] local.INFO: Correo de contacto enviado {"nombre":"Juan Pérez","email":"juan.perez@ejemplo.com","destinatario":"contacto@sjsconstrucciones.com"}
```

## Formato del Correo

El correo que recibirás tendrá:

- **Asunto**: "Nuevo mensaje de contacto - [Nombre del remitente]"
- **Reply-To**: Email del remitente (para responder directamente)
- **Cuerpo**: Template HTML con:
  - Nombre del remitente
  - Email
  - Teléfono (si se proporcionó)
  - Empresa (si se proporcionó)
  - Mensaje completo
  - Fecha y hora del envío

## Archivos Creados

```
app/
├── Http/
│   ├── Controllers/
│   │   └── ContactoController.php      # Controlador principal
│   └── Requests/
│       └── ContactoRequest.php         # Validación de datos
├── Mail/
│   └── ContactoMail.php                # Clase Mailable
resources/
└── views/
    └── emails/
        └── contacto.blade.php          # Template del correo
routes/
└── segmented/
    └── public.php                      # Ruta pública registrada
```

## Notas Importantes

1. Este es un endpoint **público** que no requiere autenticación
2. El rate limiting está configurado para prevenir spam
3. Los campos `telefono` y `empresa` son opcionales
4. El mensaje tiene un límite de 2000 caracteres
5. En modo debug (`APP_DEBUG=true`), se mostrarán detalles del error en la respuesta
