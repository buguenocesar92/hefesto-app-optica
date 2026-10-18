# API

## Cómo se habla con la API

Todas las rutas cuelgan de `/v1`. La interfaz Nuxt las reenvía quitando el
prefijo `/api`, así que el navegador llama a `/api/v1/...` y la ruta real en la
API es `/v1/...`.

```
  navegador  ->  /api/v1/pacientes   ->   API  /v1/pacientes
```

### Sesión por cookie

La sesión viaja en una cookie. El token CSRF se reenvía en la cabecera
`X-XSRF-TOKEN`.

Por eso el navegador nunca habla directo con la API: ver
`docs/01-ARQUITECTURA.md`.

### Un rechazo es 404

Cuando una policy rechaza, la respuesta es **404**, no 403. Un 403 confirmaría
que el recurso existe. Ver `docs/07-SEGURIDAD-Y-LEY-19628.md`.

## Endpoints sin sesión

| Método | Ruta | Qué hace |
|---|---|---|
| GET | `/v1/salud` | Comprobación de salud. No toca la base. |
| GET | `/v1/acceso/opticas` | Ópticas para el selector de ingreso. Lee `vw_opticas_publicas`. |
| POST | `/v1/acceso/entrar` | Inicia el flujo OIDC y devuelve la URL del proveedor. |
| GET | `/v1/acceso/callback` | Vuelve del proveedor. Abre la sesión. |
| POST | `/v1/acceso/salir` | Cierra la sesión. |

`GET /v1/acceso/opticas` es la ruta que prueba que la vista pública **se usa de
verdad**. Su respuesta tiene exactamente tres campos por óptica: `id`, `slug` y
`nombre`. Si alguna vez trae más, hay un problema.

## Sesión

| Método | Ruta | Rol mínimo |
|---|---|---|
| GET | `/v1/yo` | cualquiera con sesión |

```json
{
  "usuario": {
    "id": "...",
    "nombre": "...",
    "correo": "...",
    "rol": "optometrista",
    "rol_etiqueta": "Optometrista",
    "optica": { "id": "...", "nombre": "...", "zona_horaria": "America/Santiago" },
    "capacidades": {
      "puede_ver_pacientes": true,
      "puede_ver_recetas": true,
      "puede_emitir_recetas": true,
      "puede_registrar_control_calidad": true,
      "puede_gestionar_pedidos": true,
      "puede_registrar_ajustes": true,
      "puede_administrar_catalogo": false,
      "puede_administrar_usuarios": false,
      "puede_consultar_bitacora": false
    }
  }
}
```

Las capacidades sirven para esconder botones. **No son seguridad**: la seguridad
está en la API, que devuelve 404 cuando rechaza.

## Pacientes

| Método | Ruta | Rol mínimo |
|---|---|---|
| GET | `/v1/pacientes` | todos |
| POST | `/v1/pacientes` | todos |
| GET | `/v1/pacientes/{id}` | todos |
| PUT | `/v1/pacientes/{id}` | todos |
| POST | `/v1/pacientes/{id}/desactivar` | todos |

Filtros de `GET /v1/pacientes`: `q` (nombre, apellido o RUT), `por_pagina`.

Un paciente **no tiene graduaciones**. Sus recetas aparecen en el detalle
**solo si quien pregunta puede ver graduaciones**.

## Recetas

| Método | Ruta | Rol mínimo |
|---|---|---|
| GET | `/v1/recetas` | optometrista |
| POST | `/v1/recetas` | optometrista |
| GET | `/v1/recetas/{id}` | optometrista |
| POST | `/v1/recetas/{id}/anular` | optometrista |
| GET | `/v1/recetas/{id}/graduaciones` | optometrista |
| PUT | `/v1/recetas/{id}/graduaciones` | optometrista |

Filtros: `paciente_id`, `estado`, `solo_vencidas`, `solo_sin_plazo`,
`por_pagina`.

Todas estas rutas llevan el sufijo `.auditada` en su nombre, y el middleware con
ese nombre registra el acceso en la bitácora. Ver `docs/03-MULTITENANCY-Y-ROLES.md`.

### Vencimientos

| Método | Ruta | Rol mínimo |
|---|---|---|
| GET | `/v1/recetas-vencidas/indice` | cualquiera con sesión |

**No lleva el middleware de auditoría**, porque no abre ninguna receta: cuenta.
Muestra cuántos hay de cada tipo y si el plazo legal sigue sin verificarse.

```json
{
  "vigentes": 42,
  "por_vencer": 3,
  "vencidas": 1,
  "sin_plazo": 118,
  "dias_de_aviso": 30,
  "plazo_legal_pendiente_de_verificacion": true,
  "franjas": [
    { "etiqueta": "Hasta los 12 anios", "desde": 0, "hasta": 12, "dias": null }
  ]
}
```

Que `plazo_legal_pendiente_de_verificacion` sea `true` y `sin_plazo` sea 118
significa que el sistema **no sabe** si esas 118 recetas están vigentes. No son
118 recetas vigentes.

### Las graduaciones

`PUT /v1/recetas/{id}/graduaciones` recibe **cuatro filas**:

```json
{
  "graduaciones": [
    { "ojo": "derecho",  "meridiano": "lejos", "esfera": -2.25, "cilindro": -0.50, "eje": 180 },
    { "ojo": "derecho",  "meridiano": "cerca", "esfera": -1.25, "cilindro": -0.50, "eje": 180, "adiccion": 1.00 },
    { "ojo": "izquierdo", "meridiano": "lejos", "esfera": -1.75, "cilindro": -0.25, "eje": 170 },
    { "ojo": "izquierdo", "meridiano": "cerca", "esfera": -0.75, "cilindro": -0.25, "eje": 170, "adiccion": 1.00 }
  ]
}
```

Las cuatro son obligatorias. La adición va **solo** en el meridiano de cerca, y
la base lo impide con una restricción `CHECK`. Ver `docs/02-MODELO-DE-DATOS.md`.

### Emitir sin fecha de vencimiento

```json
{
  "paciente_id": "...",
  "numero": "R-00001",
  "fecha_emision": "2026-10-18",
  "fecha_vencimiento": null,
  "motivo_sin_vencimiento": "Plazo legal pendiente de verificación."
}
```

Si no se manda `fecha_vencimiento` y tampoco `motivo_sin_vencimiento`, la petición se rechaza. Ver
`docs/06-PRIMERA-RECETA.md`.

## Catálogo

| Método | Ruta | Rol mínimo |
|---|---|---|
| GET | `/v1/armazones` | todos |
| POST | `/v1/armazones` | administración |
| GET | `/v1/armazones/{id}` | todos |
| PUT | `/v1/armazones/{id}` | administración |
| POST | `/v1/armazones/{id}/desactivar` | administración |
| GET | `/v1/lentes` | todos |
| POST | `/v1/lentes` | administración |
| GET | `/v1/lentes/{id}` | todos |
| PUT | `/v1/lentes/{id}` | administración |
| POST | `/v1/lentes/{id}/desactivar` | administración |

El catálogo **no se borra, se desactiva**. Un armazón que ya se pidió a un
laboratorio tiene pedidos que lo referencian.

El detalle de un armazón trae el ancho de caja ya calculado:

```json
{
  "ancho_varilla_mm": 52.0,
  "puente_mm": 18.0,
  "alto_mm": 40.0,
  "ancho_de_caja_mm": 70.0,
  "medidas_legibles": { "...": "en texto, con notacion chilena" }
}
```

El detalle de un lente trae qué le falta:

```json
{
  "indice_refraccion": null,
  "diametro_corte_mm": null,
  "datos_incompletos": ["indice_refraccion", "diametro_corte_mm"],
  "puede_verificar_compatibilidad": false
}
```

### Verificación de compatibilidad

```
POST /v1/compatibilidad
{ "armazon_id": "...", "lente_id": "..." }
```

Rol mínimo: cualquiera con sesión. No muta nada.

Devuelve **el desglose del cálculo**, no un sí o un no:

```json
{
  "veredicto": "no_verificable",
  "veredicto_etiqueta": "No verificable todavia",
  "motivo": "Falta el margen efectivo minimo del lente ...",
  "datos_faltantes": ["margen_efectivo_minimo_mm"],
  "desglose": {
    "ancho_de_caja_mm": 70.0,
    "alto_de_caja_mm": 40.0,
    "margen_efectivo_minimo_mm": null,
    "lente": { "diametro_corte_mm": 75.0 }
  }
}
```

Los tres veredictos posibles:

| Veredicto | Qué significa | ¿Despacha? |
|---|---|---|
| `compatible` | La pastilla cubre la caja con holgura. | Sí |
| `incompatible` | No alcanza. | No |
| `no_verificable` | Falta un dato. **No es un sí.** | No |

## Pedidos a laboratorio

| Método | Ruta | Rol mínimo |
|---|---|---|
| GET | `/v1/pedidos` | todos |
| POST | `/v1/pedidos` | mostrador u optometrista |
| GET | `/v1/pedidos/{id}` | todos |
| PUT | `/v1/pedidos/{id}` | mostrador u optometrista |
| POST | `/v1/pedidos/{id}/recibir` | mostrador u optometrista |
| POST | `/v1/pedidos/{id}/cancelar` | mostrador u optometrista |

Filtros: `estado`, `en_curso`, `por_pagina`.

Al crear un pedido, la API **calcula la compatibilidad en el servidor** y guarda
el veredicto con el desglose. No se recibe del navegador.

La respuesta incluye `compatibilidad.resultado`, que puede ser
`no_verificable`, y `puede_entregarse`.

### La graduación del pedido

Cada renglón trae `graduacion_pedida`, que es una **copia** de lo que salió de
la óptica.

**Solo se incluye si quien pregunta puede leer graduaciones.** Sin ese filtro, el
mostrador recibiría la graduación del paciente por un endpoint que sí la
autoriza.

## Control de calidad

| Método | Ruta | Rol mínimo |
|---|---|---|
| GET | `/v1/controles-calidad` | optometrista |
| POST | `/v1/controles-calidad/comparar` | optometrista |
| POST | `/v1/controles-calidad` | optometrista |
| GET | `/v1/controles-calidad/{id}` | optometrista |

`/comparar` **no guarda nada**: sirve para ver las diferencias antes de poner el
veredicto.

Las diferencias **las calcula el servidor**, con
`App\Services\Calidad\ComparadorDeGraduaciones`, contra la foto que se guardó
en el pedido. No se reciben en el cuerpo.

```json
{
  "diferencias": [
    { "ojo": "derecho", "meridiano": "lejos", "campo": "esfera",
      "pedido": -2.25, "recibido": -2.75, "diferencia": -0.5 }
  ],
  "cantidad": 1,
  "tolerancia_configurada": false,
  "veredicto_pendiente_de_decision": true
}
```

`tolerancia_configurada: false` significa que el sistema **no dice** cuáles son
aceptables. Solo las muestra.

Un control por pedido. Si el resultado es `no_conforme`, la corrección es un
pedido **nuevo**.

## Entregas y ajustes

| Método | Ruta | Rol mínimo |
|---|---|---|
| GET | `/v1/entregas` | todos |
| POST | `/v1/entregas` | mostrador u optometrista |
| POST | `/v1/entregas/{id}/entregar` | mostrador u optometrista, **y el control de calidad en firme** |
| GET | `/v1/ajustes` | todos |
| POST | `/v1/ajustes` | mostrador u optometrista |
| POST | `/v1/ajustes/{id}/cerrar` | mostrador u optometrista |

`POST /v1/entregas/{id}/entregar` devuelve **404** si el pedido no tiene control
de calidad o si salió `no_conforme`.

Un ajuste solo se registra sobre una entrega **ya hecha**.

`GET /v1/ajustes` devuelve además `motivo_mas_frecuente` y
`motivos_sugeridos`.

## Administración

Rutas bajo el middleware `admin`. Un rechazo responde 404.

| Método | Ruta |
|---|---|
| GET | `/v1/admin/usuarios` |
| POST | `/v1/admin/usuarios` |
| POST | `/v1/admin/usuarios/{id}/desactivar` |
| GET | `/v1/admin/bitacora` |

Nadie puede desactivarse a sí mismo.

`POST /v1/admin/usuarios` acepta un `rol`, pero es solo para dejar la cuenta
costeada: **el rol lo decide Authentik** y se resuelve en cada ingreso.

### La bitácora

Filtros: `usuario_id`, `recurso`, `permitido`, `por_pagina`.

Trae quién, cuándo, sobre qué recurso y si se le permitió. **Ningún valor de
graduación.**

## Formato de las respuestas

Las listas usan el mismo sobre:

```json
{
  "datos": [ ... ],
  "paginacion": { "pagina": 1, "por_pagina": 50, "total": 137 }
}
```

### Errores

```json
{ "mensaje": "..." , "errors": { "campo": ["..."] } }
```

Los mensajes van en español. Las validaciones devuelven `422`, y un rechazo de
autorización devuelve `404` (no `403`).

## El contrato en TypeScript

`web/app/tipos/api.ts` declara los tipos, incluidos los `| null`.

Cuando el contrato cambie, **los dos lados a la vez**: el controlador y ese
archivo. Es el único punto donde la interfaz y la API se tocan.

Los `| null` no son descuidos: son valores que el sistema deja vacíos a
propósito porque no se pudo verificarlos. Ver `docs/05-CONFIGURACION.md`.