# Hito temporal — Indirectos y matriz anidada

Nota de decisiones. No sustituye `docs/context/`. Sirve para no perder lo cerrado en la conversación de octubre 2026.

Referencia de comportamiento: hoja de análisis P1.6 (Estación de servicios Charay) y el Manual OPUS Presupuesto Programable, Módulo 1.

## Qué entra

- Captura de la matriz en **escritorio**, en una sola pantalla.
- Elegir el renglón del catálogo **propio** de la empresa o del catálogo **global** (productos publicados, conexión NexProv).
- Guardar y mostrar la **clave** del renglón, como en OPUS.
- **Matriz dentro de matriz:** el renglón muestra el costo calculado y se puede abrir.
- **Indirectos** en una sección del presupuesto, con override por matriz.

## Pantalla de escritorio

El compuesto deja de ser un asistente de tres pasos.

- Arriba: clave, nombre y unidad de la matriz.
- Centro: tabla con clave, descripción, unidad, cantidad, costo e importe. Agrupada por tipo, con subtotal de grupo.
- Agregar abre un buscador al lado: **Propio**, **Global** y **Básicos**. Básicos se ve y no se usa.
- Al elegir, la fila se llena sola. En la fila solo se escribe la cantidad.
- Si lo elegido ya tiene matriz, una flecha lo abre en ese mismo panel.
- Abajo, fijo: costo directo, indirectos y precio.

El concepto sin matriz no cambia de pantalla.

## Indirectos

Sección en la información general del presupuesto. Cuatro porcentajes, en cero al empezar: oficina, campo, utilidad y cargos.

Dentro de la matriz, tres estados:

- **General.** Usa los de la sección. Es el estado inicial.
- **Propios.** Porcentajes de esa matriz. Un cambio general no la toca.
- **Volver al general.** Suelta los propios.

Cuenta, redondeando cada importe a dos decimales. Prueba P1.6:

| Paso | Cuenta | Resultado |
|---|---|---|
| Costo directo | suma de la matriz | 462.70 |
| Oficina 2 % y campo 10 % | sobre el costo directo | 9.25 y 46.27 |
| Utilidad 10 % | sobre 518.22 | 51.82 |
| Cargos 5.94 % | sobre 570.04 | 33.86 |
| Precio unitario | | 603.90 |

El costo directo sigue guardado como suma de la matriz del catálogo. El precio de la línea del presupuesto es el costo directo más el pie. IVA y descuento del documento se aplican después.

Un renglón que apunta a otra matriz usa el costo directo del hijo, no su precio con indirectos.

Los porcentajes generales viven en el presupuesto. El estado propio vive en la línea, en el compuesto del catálogo y en la línea de plantilla. Un presupuesto ya guardado conserva su copia.

## Fuera de este hito

- **Porcentaje de mano de obra.** Espera a que la cuadrilla esté en la matriz. La base es el importe de la cuadrilla. No adelantarlo.
- **Explosión de insumos.** Pendiente. Es la lista de compras de toda la obra y necesita recorrer las matrices de adentro.
- **Básicos** como catálogo propio. Pendiente. El selector ya reserva el lugar.
- **Cuadrillas y capítulos.** Pendientes.
