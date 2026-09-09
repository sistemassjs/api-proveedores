# Migración de SPP Antiguas al Sistema de Pagos

## 📋 Problema

Las SPP (Solicitudes de Pago a Proveedores) que se registraron **antes** de la implementación del módulo de pagos no tienen registros en las tablas `pagos_spp` y `pago_solicitud_pago`. Esto causa problemas de compatibilidad cuando:

- Se intenta consultar el historial de pagos de una SPP
- Se generan reportes de contabilidad
- Se busca información detallada de cómo se pagó una SPP

## ✅ Solución

Se ha creado un comando Artisan que **migra automáticamente** las SPP antiguas al nuevo sistema de pagos, generando los registros correspondientes para mantener la compatibilidad.

### Características:

- ✨ Identifica SPP sin registros de pago automáticamente
- 🔒 Modo `--dry-run` para probar sin hacer cambios
- 📊 Reportes detallados de la migración
- ⚡ Procesamiento por lotes para mejor rendimiento
- 🛡️ Transacciones de base de datos para seguridad
- 📝 Log completo de errores

## 🚀 Uso del Comando

### 1. Modo Prueba (Recomendado primero)

Ejecuta el comando en modo `--dry-run` para ver qué SPP se migrarían **sin hacer cambios**:

```bash
php artisan spp:migrar-antiguas --dry-run
```

### 2. Migración Real

Una vez verificado en modo prueba, ejecuta la migración real:

```bash
php artisan spp:migrar-antiguas
```

### 3. Migración con Confirmación Automática

Para scripts o automatización (sin solicitar confirmación):

```bash
php artisan spp:migrar-antiguas --force
```

### 4. Opciones Avanzadas

```bash
# Procesar en lotes de 50 SPP a la vez
php artisan spp:migrar-antiguas --chunk=50

# Combinar opciones
php artisan spp:migrar-antiguas --dry-run --chunk=50
```

## 📊 Qué hace el comando

### Identifica SPP candidatas:

El comando busca SPP que cumplan **todas** estas condiciones:

1. **Estado PAGADO** o **monto_abonado > 0** o **saldo_pendiente = 0**
2. **NO tienen registros** en las tablas de pagos (`pagos_spp` y `pago_solicitud_pago`)
3. Tienen un **monto_total > 0**

### Para cada SPP candidata:

1. ✅ **Valida** que tenga proveedor y empresa constructora
2. ✅ **Crea un registro de pago** en `pagos_spp` con:
   - Folio único generado automáticamente
   - Fecha de pago (usa `fecha_pago` de la SPP o su fecha de creación)
   - Monto total = monto_abonado (o monto_total si no hay abonos)
   - Referencia: `MIGRACION-{folio_spp}`
   - Observaciones indicando que es una migración histórica

3. ✅ **Crea la relación** en `pago_solicitud_pago` con:
   - Monto aplicado
   - Saldo inicial
   - Estado del pago (completado o parcial)
   - Notas de migración automática

4. ✅ **Actualiza los campos deprecados** de la SPP para consistencia

## 🧪 Pruebas

### Crear SPP de Prueba

Se incluye un seeder para crear SPP antiguas de prueba:

```bash
php artisan db:seed --class=SppAntiguasSeeder
```

Este seeder crea varios escenarios:
- SPP completamente pagadas
- SPP parcialmente pagadas
- SPP marcadas como pagadas sin abonos
- SPP con comprobantes adjuntos

### Flujo de Prueba Completo

```bash
# 1. Crear SPP antiguas de prueba
php artisan db:seed --class=SppAntiguasSeeder

# 2. Ver qué se migraría (sin hacer cambios)
php artisan spp:migrar-antiguas --dry-run

# 3. Ejecutar la migración
php artisan spp:migrar-antiguas

# 4. Verificar en la base de datos
# SELECT * FROM pagos_spp WHERE observaciones LIKE '%migrado%';
# SELECT * FROM pago_solicitud_pago WHERE notas LIKE '%migrado%';
```

## 📈 Salida del Comando

### Ejemplo en Modo Dry-Run:

```
🚀 Iniciando migración de SPP antiguas al sistema de pagos...

⚠️  MODO DRY-RUN: No se realizarán cambios en la base de datos

🔍 Buscando SPP sin registros de pago...
📊 Se encontraron 15 SPP candidatas para migración

+----------------+------------+
| Concepto       | Valor      |
+----------------+------------+
| Total SPP      | 15         |
| Monto total    | $450,000.00|
| Monto abonado  | $380,000.00|
+----------------+------------+

📋 Distribución por estado:
   • PAGADO: 12
   • AUTORIZADA: 3

⚙️  Procesando SPP...
[████████████████████] 100%

✅ Migración completada (MODO PRUEBA)

+---------------------------+----------+
| Concepto                  | Cantidad |
+---------------------------+----------+
| ✓ SPP migradas exitosamente| 15      |
| ✗ SPP con errores          | 0       |
| ○ SPP omitidas             | 0       |
+---------------------------+----------+
```

## 🔍 Verificación Post-Migración

### Consultar SPP migradas:

```php
// Obtener todas las SPP que ahora tienen pagos
$sppMigradas = SolicitudPago::has('pagos')
    ->whereHas('pagos', function($q) {
        $q->where('referencia_pago', 'like', 'MIGRACION-%');
    })
    ->get();

// Ver detalles de una SPP migrada
$spp = SolicitudPago::with(['pagos', 'pagos.pivot'])->find($id);
```

### Consulta SQL directa:

```sql
-- Ver pagos migrados
SELECT * FROM pagos_spp 
WHERE observaciones LIKE '%migrado%'
ORDER BY fecha_registro DESC;

-- Ver relaciones creadas
SELECT 
    psp.id,
    psp.solicitud_pago_id,
    psp.pago_spp_id,
    psp.monto_aplicado,
    psp.saldo_inicial,
    psp.estado_pago,
    ps.folio_pago_spp_consecutivo,
    sp.numero_folio_solicitud
FROM pago_solicitud_pago psp
JOIN pagos_spp ps ON psp.pago_spp_id = ps.id
JOIN solicitudes_pago sp ON psp.solicitud_pago_id = sp.id
WHERE psp.notas LIKE '%migrado%';
```

## ⚠️ Consideraciones Importantes

### Campos Deprecados

Los campos `monto_abonado`, `saldo_pendiente` y `pago_completo` en la tabla `solicitudes_pago` están **deprecados** pero se mantienen por compatibilidad. El comando los actualiza para mantener consistencia, pero:

- ✅ **Usar**: `$spp->calcularMontoAbonado()`
- ✅ **Usar**: `$spp->calcularSaldoRestante()`
- ✅ **Usar**: `$spp->estaPagadaCompletamente()`

### Comprobantes de Pago

Si una SPP antigua tiene `ruta_archivo_comprobante_pago`, se usará para el pago migrado. Si no:
- Se genera una ruta placeholder: `comprobantes/historicos/spp_{id}_migrado.pdf`
- Los archivos reales deben gestionarse manualmente si es necesario

### Folios de Pago

El comando intenta obtener el folio siguiente de la empresa constructora. Si falla:
- Se genera un folio con formato: `HIST-{folio_spp}-{fecha}`

## 🐛 Solución de Problemas

### Error: "SPP sin proveedor asociado"

```bash
# La SPP no tiene proveedor_id o el proveedor no existe
# Solución: Actualizar la SPP manualmente antes de migrar
UPDATE solicitudes_pago 
SET proveedor_id = ? 
WHERE id = ?;
```

### Error: "Monto abonado mayor al monto total"

```bash
# Inconsistencia en los datos
# Solución: Corregir los montos antes de migrar
UPDATE solicitudes_pago 
SET monto_abonado = monto_total 
WHERE monto_abonado > monto_total;
```

### Ver logs de errores:

```bash
tail -f storage/logs/laravel.log | grep "Error al migrar SPP"
```

## 📝 Notas Técnicas

### Estructura de la Migración

```
solicitudes_pago (SPP antigua)
    ↓ (migración)
pagos_spp (nuevo registro de pago)
    ↓ (relación)
pago_solicitud_pago (tabla pivot)
    ↓ (actualización)
solicitudes_pago (campos deprecados actualizados)
```

### Transacciones

Cada SPP se procesa en una **transacción independiente**. Si una falla:
- Se hace rollback solo de esa SPP
- Las demás continúan procesándose
- Se registra el error en logs

### Performance

- Procesa por lotes (configurable con `--chunk`)
- Usa `with()` para eager loading
- Minimiza consultas dentro de transacciones

## 🤝 Contribuir

Si encuentras casos especiales que el comando no maneja:
1. Documenta el escenario
2. Añade validaciones al comando
3. Crea tests correspondientes

## 📚 Referencias

- Modelo: `App\Models\SolicitudPago`
- Modelo: `App\Models\PagoSPP`
- Modelo: `App\Models\PagoSolicitudPago`
- Comando: `App\Console\Commands\MigrarSppAntiguasAPagos`
- Seeder: `Database\Seeders\SppAntiguasSeeder`
