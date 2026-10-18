/**
 * Tipos que viajan entre la interfaz y la API de OpticaHefesto.
 *
 * Reflejan lo que devuelve `App\Http\Controllers`. Cuando el contrato cambie,
 * el lugar que hay que tocar es este archivo y el controlador, en ese orden.
 *
 * LO QUE ESTA TIPADO COMO NULL Y POR QUE
 * --------------------------------------
 * Varios campos son `| null` y NO es descuido: son valores que el sistema
 * deja vacios a proposito porque no se pudo verificar. Escribirlos como
 * `string` obligaria a la interfaz a inventar un valor de pantalla.
 *
 *   - `Vencimiento.plazo_dias`: el plazo legal por franja etaria no esta
 *     verificado. Ver docs/06.
 *   - `Compatibilidad.datos_faltantes`: falta el diametro de corte del lente o
 *     el margen efectivo. Ver docs/06.
 *   - `Receta.fecha_vencimiento`: nula cuando no hay plazo configurado.
 */

// ---------------------------------------------------------------------------
// Sesion
// ---------------------------------------------------------------------------

export type Rol = 'mostrador' | 'optometrista' | 'administrador'

export interface Capacidades {
  puede_ver_pacientes: boolean
  puede_ver_recetas: boolean
  puede_emitir_recetas: boolean
  puede_registrar_control_calidad: boolean
  puede_gestionar_pedidos: boolean
  puede_registrar_ajustes: boolean
  puede_administrar_catalogo: boolean
  puede_administrar_usuarios: boolean
  puede_consultar_bitacora: boolean
}

export interface Usuario {
  id: string
  nombre: string
  correo: string
  rol: Rol
  rol_etiqueta: string
  optica: {
    id: string
    nombre: string
    zona_horaria: string
  } | null
  capacidades: Capacidades
}

/** Lo que devuelve `GET /api/v1/acceso/opticas`. Sale de `vw_opticas_publicas`. */
export interface OpticaPublica {
  id: string
  slug: string
  nombre: string
}

// ---------------------------------------------------------------------------
// Recetas y graduaciones
// ---------------------------------------------------------------------------

export type Ojo = 'derecho' | 'izquierdo'
export type Meridiano = 'lejos' | 'cerca'

/**
 * Una fila de graduacion: un ojo, un meridiano.
 *
 * Los valores llegan como TEXTO en notacion chilena (`-2,25`) porque asi los
 * escribe y los lee el optico. La conversion a numero la hace quien lo
 * necesita.
 */
export interface Graduacion {
  ojo: Ojo
  ojo_etiqueta: string
  meridiano: Meridiano
  meridiano_etiqueta: string
  esfera: string
  cilindro: string
  eje: number
  adicion?: string | null
}

export type EstadoVencimiento =
  | 'vigente'
  | 'por_vencer'
  | 'vencida'
  | 'sin_plazo'
  | 'anulada'

/**
 * Estado de vigencia de una receta.
 *
 * `sin_plazo` no es un caso borde: es la respuesta por omision mientras el
 * plazo legal no este verificado. Significa "el sistema no sabe cuanto vale",
 * y por eso NO bloquea el despacho. Ver docs/06.
 */
export interface Vencimiento {
  estado: EstadoVencimiento
  fecha_vencimiento: string | null
  dias_restantes: number | null
  edad_anios: number | null
  franja_etaria: string | null
  plazo_dias: number | null
  pendiente_de_verificacion: boolean
  permite_despachar: boolean
  explicacion: string | null
}

export interface FranjaEtaria {
  etiqueta: string
  desde: number
  hasta: number
  /** Null mientras el plazo legal no se haya verificado. */
  dias: number | null
}

export interface ResumenRecetas {
  vigente: number
  por_vencer: number
  vencidas: number
  sin_plazo: number
  dias_de_aviso: number
  plazo_legal_pendiente_de_verificacion: boolean
  franjas: FranjaEtaria[]
}

export interface Receta {
  id: string
  numero: string
  paciente_id: string
  paciente: string | null
  optometrista_id: string
  optometrista: string | null
  fecha_emision: string
  fecha_vencimiento: string | null
  motivo_sin_vencimiento: string | null
  estado: string
  estado_etiqueta: string
  distancia_interpupilar_mm: number | null
  observaciones: string | null
  graduaciones: Graduacion[]
  vencimiento: Vencimiento
}

// ---------------------------------------------------------------------------
// Pacientes
// ---------------------------------------------------------------------------

export interface Paciente {
  id: string
  rut: string | null
  nombre: string
  nombres: string
  apellidos: string
  fecha_nacimiento: string | null
  edad_anios: number | null
  telefono: string | null
  correo: string | null
  activo: boolean
  ultima_receta?: {
    id: string
    numero: string
    fecha_emision: string
    estado: string
    vencimiento: Vencimiento
  } | null
}

// ---------------------------------------------------------------------------
// Catalogo
// ---------------------------------------------------------------------------

/** Medidas de caja del armazon, en texto con notacion chilena. */
export interface MedidasArmazon {
  ancho_varilla_mm: string
  puente_mm: string
  alto_mm: string
  ancho_de_caja_mm: string
}

export interface Armazon {
  id: string
  codigo: string
  nombre: string
  marca: string | null
  modelo: string | null
  material: string | null
  color: string | null
  ancho_varilla_mm: number
  puente_mm: number
  alto_mm: number
  ancho_de_caja_mm: number
  medidas_legibles: MedidasArmazon
  activo: boolean
}

export interface Lente {
  id: string
  codigo: string
  nombre: string
  marca: string | null
  material: string | null
  /** Null hasta que la optica lo cargue desde la ficha del fabricante. */
  indice_refraccion: number | null
  tratamientos: string[]
  /** Null mientras no se sepa de que diametro se mecaniza el lente. */
  diametro_corte_mm: number | null
  es_progresivo: boolean
  activo: boolean
  /** Campos que le faltan al registro para estar completo. */
  datos_incompletos: string[]
  puede_verificar_compatibilidad: boolean
}

// ---------------------------------------------------------------------------
// Verificacion de compatibilidad
// ---------------------------------------------------------------------------

export type VeredictoCompatibilidad = 'compatible' | 'incompatible' | 'no_verificable'

/**
 * Resultado de verificar si un armazon y un lente encajan.
 *
 * `desglose` trae los numeros del calculo, no solo el veredicto: cuando el
 * veredicto es `incompatible` o `no_verificable`, lo que hace falta es entender
 * que numero salio mal. Ver docs/06.
 */
export interface ResultadoCompatibilidad {
  veredicto: VeredictoCompatibilidad
  veredicto_etiqueta: string
  motivo: string | null
  /** Campos que faltaron para poder dar un veredicto. */
  datos_faltantes: string[]
  desglose: {
    armazon?: {
      codigo: string
      nombre: string
      ancho_varilla_mm: number
      puente_mm: number
      alto_mm: number
    }
    lente?: {
      codigo: string
      nombre: string
      material: string | null
      indice_refraccion: number | null
      es_progresivo: boolean
      diametro_corte_mm: number | null
    }
    ancho_de_caja_mm?: number
    alto_de_caja_mm?: number
    margen_efectivo_minimo_mm?: number | null
    diametro_horizontal_requerido_mm?: number
    diametro_vertical_requerido_mm?: number
    diametro_minimo_requerido_mm?: number
    eje_que_manda?: 'ancho_de_caja' | 'alto_de_caja'
    holgura_mm?: number
  }
}

// ---------------------------------------------------------------------------
// Pedidos a laboratorio
// ---------------------------------------------------------------------------

export type EstadoPedido =
  | 'borrador'
  | 'enviado'
  | 'aceptado'
  | 'en_produccion'
  | 'recibido'
  | 'cancelado'

export type OrigenFechaPrometida = 'configuracion' | 'manual' | 'pendiente'

export interface ItemPedido {
  id: string
  ojo: Ojo
  ojo_etiqueta: string
  lente_id: string | null
  cantidad: number
  tiene_graduacion_recibida: boolean
  /** Solo llega si quien mira puede leer graduaciones. */
  graduacion_pedida?: Record<string, Record<string, number>> | null
  graduacion_recibida?: Record<string, Record<string, number>> | null
}

export interface Pedido {
  id: string
  codigo: string
  paciente_id: string
  paciente: string | null
  receta_id: string | null
  armazon_id: string | null
  lente_id: string | null
  estado: EstadoPedido
  estado_etiqueta: string
  laboratorio: string | null
  fecha_envio: string | null
  /** Null mientras no se negocie con el laboratorio. Ver docs/06. */
  fecha_prometida: string | null
  origen_fecha_prometida: OrigenFechaPrometida
  fecha_recepcion: string | null
  notas: string | null
  esta_atrasado: boolean
  dias_para_promesa: number | null
  compatibilidad: {
    resultado: VeredictoCompatibilidad | null
    etiqueta: string | null
    desglose?: unknown | null
  }
  puede_entregarse: boolean
  items: ItemPedido[]
}

// ---------------------------------------------------------------------------
// Control de calidad
// ---------------------------------------------------------------------------

export type ResultadoControlCalidad = 'conforme' | 'con_diferencias' | 'no_conforme'

export interface DiferenciaGraduacion {
  ojo: Ojo
  meridiano: Meridiano | null
  campo: string
  pedido: number | null
  recibido: number | null
  diferencia: number | null
}

export interface ControlCalidad {
  id: string
  pedido_id: string
  pedido_codigo: string | null
  resultado: ResultadoControlCalidad
  resultado_etiqueta: string
  permite_entregar: boolean
  fecha_revision: string
  revisado_por: string | null
  diferencias: DiferenciaGraduacion[]
  diferencias_por_ojo: Record<string, DiferenciaGraduacion[]>
  cantidad_diferencias: number
  observaciones: string | null
  pedido_bloqueado_para_entrega: boolean
}

// ---------------------------------------------------------------------------
// Entregas y ajustes
// ---------------------------------------------------------------------------

export type EstadoEntrega = 'registrada' | 'entregada' | 'devuelta'
export type EstadoAjuste = 'pendiente' | 'realizado' | 'no_requerido'

export interface Ajuste {
  id: string
  entrega_id: string
  paciente: string | null
  fecha: string
  motivo: string
  descripcion: string | null
  estado: EstadoAjuste
  estado_etiqueta: string
  lente_sustituido: boolean
  observaciones: string | null
}

export interface Entrega {
  id: string
  pedido_id: string
  pedido_codigo: string | null
  paciente_id: string
  paciente: string | null
  estado: EstadoEntrega
  estado_etiqueta: string
  fecha_registro: string
  fecha_entrega: string | null
  dias_desde_entrega: number
  recibido_por: string | null
  observaciones: string | null
  ajustes: Ajuste[]
  ajustes_abiertos: number
  admite_ajuste: boolean
}

// ---------------------------------------------------------------------------
// Envolturas de respuesta
// ---------------------------------------------------------------------------

export interface Paginado<T> {
  datos: T[]
  paginacion: {
    pagina: number
    por_pagina: number
    total: number
  }
}

export interface RespuestaMensaje {
  mensaje: string
}