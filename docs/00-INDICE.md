# Índice

Documentación de **OpticaHefesto**, aplicación de óptica y contactología para una
pyme chilena. Software original de Hefesto Studio.

## Orden de lectura

| # | Documento | Cuándo leerlo |
|---|---|---|
| 00 | [Índice](00-INDICE.md) | Ahora. Este archivo. |
| 01 | [Arquitectura](01-ARQUITECTURA.md) | Para entender cómo encajan las piezas. |
| 02 | [Modelo de datos](02-MODELO-DE-DATOS.md) | Antes de tocar una migración. |
| 03 | [Multitenancy y roles](03-MULTITENANCY-Y-ROLES.md) | Antes de tocar el aislamiento. |
| 04 | [Instalación](04-INSTALACION.md) | Para levantarla. |
| 05 | [Configuración](05-CONFIGURACION.md) | Para entender qué se puede cambiar. |
| 06 | [Primera receta](06-PRIMERA-RECETA.md) | Para entender el producto. |
| 07 | [Seguridad y Ley 19.628](07-SEGURIDAD-Y-LEY-19628.md) | Antes de tocar el acceso a datos. |
| 08 | [Respaldos](08-RESPALDOS.md) | Antes de poner datos reales. |
| 09 | [API](09-API.md) | Para consumir la API. |
| 10 | [Pruebas y estado](10-PRUEBAS-Y-ESTADO.md) | Para saber qué está probado. |

## Las tres ideas que resumen la aplicación

1. **Una receta es un documento con vida propia.** Se emite en una fecha, tiene
   un plazo de vigencia y sirve para despachar lentes. Por eso tiene número, no
   se edita y se anula en vez de borrarse.

2. **El plazo de vigencia está pendiente de verificación.** Es norma sanitaria
   chilena. En la máquina donde se escribió esta aplicación no se pudo
   verificar, así que nace en `null` y el sistema avisa en vez de inventar una
   fecha. Ver [06](06-PRIMERA-RECETA.md).

3. **La compatibilidad armazón/lente devuelve un desglose, no un sí o un no.**
   Cuando falta un dato devuelve `no_verificable` con la parte geométrica ya
   calculada. Un sistema que dice "sí" sin el dato está adivinando, y adivinar
   acá significa fabricar un lente que no entra. Ver
   [06](06-PRIMERA-RECETA.md).

## Convenciones de este proyecto

- Todo en español de Chile, con tildes.
- Diagramas en ASCII puro, sin caracteres de dibujo Unicode.
- Los diagramas usan ASCII porque las tildes y los caracteres especiales se
  rompen al copiar y pegar desde una terminal de Windows, que es desde donde
  se lee casi siempre este código.
- Nada se presenta como probado que no se haya ejecutado. Ver
  [10](10-PRUEBAS-Y-ESTADO.md).
- Los datos no verificados quedan en `null` y marcados como pendientes.

## Glosario

| Término | Qué es |
|---|---|
| Graduación | Los valores ópticos de una persona: esfera, cilindro, eje y adición. |
| Meridiano | Una de las dos direcciones de mirar: **lejos** o **cerca**. |
| Medidas de caja | Las tres medidas de un armazón: ancho de varilla, puente y alto. |
| Diámetro de corte | De qué diámetro se mecaniza el lente. Si es menor que lo que pide el armazón, no entra. |
| Margen efectivo | Cuánto necesita el lente para cubrir el centro óptico y alcanzar el borde del armazón. |
| Margen efectivo | Cuánto necesita el lente para cubrir el centro óptico y alcanzar el borde del armazón. |
| Armazón | La montura del lente. |
| Rótulo | La etiqueta que trae el lente del laboratorio, donde va la graduación. |
| Tenant | La óptica: la empresa a la que pertenece cada fila. |

## Lo que esta versión NO tiene

Para que nadie lo busque y no lo encuentre:

- **No hay facturación, precios ni cobros.** Hay códigos, pacientes y
  Prescription, pero no hay facturas.
- **No hay inventario con stock.** El catálogo de armazones y lentes existe con
  sus medidas y sus características, pero no lleva cantidades ni costo.
- **No hay compra a proveedores.** El "laboratorio" del pedido es un texto.
- **No hay agenda de atenciones ni horas.** La aplicación es sobre recetas y
  lentes, no sobre turnos.
- **No hay carga de documentos ni imágenes.**

Lo que sí tiene, completo: pacientes, recetas con graduación por ojo y
meridiano, vencimiento, catálogo con medidas, verificación de compatibilidad,
pedidos a laboratorio, recepción con control de calidad, entregas y ajustes.