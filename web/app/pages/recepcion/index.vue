<script setup lang="ts">
import type { ControlCalidad, DiferenciaGraduacion, Paginado, Pedido } from '~/tipos/api';

/**
 * Recepción y control de calidad.
 *
 * ESTA PANTALLA ES LA QUE EVITA EL RECLAMO
 * ---------------------------------------
 * Si el laboratorio mandó la graduación equivocada, el momento de enterarse es
 * ANTES de entregarle los lentes al cliente. Después no hay reclamo que
 * solver.
 *
 * EL SISTEMA COMPARA, LA PERSONA DECIDE
 * -------------------------------------
 * Las diferencias se calculan en el servidor y se muestran una por una. El
 * veredicto lo pone el optometrista: la tolerancia con que se acepta una
 * diferencia nace en `null` porque es una decisión clínica.
 *
 * Ver docs/06.
 */
definePageMeta({ middleware: 'auth' });

const { puedeVerRecetas } = useSesion();

const recibidos = ref<Pedido[]>([]);
const controles = ref<Paginado<ControlCalidad> | null>(null);
const cargando = ref(true);
const error = ref<string | null>(null);

const revision = reactive({
  pedido_id: '',
  resultado: 'conforme',
  observaciones: '',
  diferencias: [] as DiferenciaGraduacion[],
  guardando: false,
});

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    if (puedeVerRecetas.value) {
      controles.value = await $fetch<Paginado<ControlCalidad>>('/api/v1/controles-calidad');
    }

    const respuesta = await $fetch<Paginado<Pedido>>('/api/v1/pedidos?estado=recibido');
    recibidos.value = respuesta.datos;
  } catch {
    error.value = 'No se pudo cargar la recepción.';
  } finally {
    cargando.value = false;
  }
}

/**
 * Pide al servidor la comparación, sin guardarla.
 *
 * El endpoint `comparar` no muta nada: sirve para ver las diferencias antes de
 * poner el veredicto, no después.
 */
async function comparar(): Promise<void> {
  if (!revision.pedido_id) {
    error.value = 'Elija un pedido.';
    return;
  }

  try {
    const respuesta = await $fetch<{
      diferencias: DiferenciaGraduacion[];
      cantidad: number;
      tolerancia_configurada: boolean;
    }>('/api/v1/controles-calidad/comparar', {
      method: 'POST',
      body: { pedido_id: revision.pedido_id },
    });

    revision.diferencias = respuesta.diferencias;
  } catch {
    error.value = 'No se pudo comparar el pedido.';
  }
}

async function registrar(): Promise<void> {
  revision.guardando = true;
  error.value = null;

  try {
    await $fetch('/api/v1/controles-calidad', {
      method: 'POST',
      body: {
        pedido_id: revision.pedido_id,
        resultado: revision.resultado,
        fecha_revision: new Date().toISOString().slice(0, 10),
        observaciones: revision.observaciones || null,
      },
    });

    revision.pedido_id = '';
    revision.observaciones = '';
    revision.diferencias = [];

    await cargar();
  } catch (e: any) {
    error.value = e?.data?.message ?? 'No se pudo registrar el control de calidad.';
  } finally {
    revision.guardando = false;
  }
}

onMounted(cargar);
</script>

<template>
  <div>
    <h1>Recepción y control de calidad</h1>

    <p class="tenue">
      Lo que llega del laboratorio se revisa antes de entregarse. Un pedido
      «no conforme» no se puede entregar: por eso existe esta pantalla.
    </p>

    <div v-if="error" class="aviso-error">{{ error }}</div>
    <div v-if="cargando" class="vacio">Cargando…</div>

    <template v-else-if="puedeVerRecetas">
      <div class="marco">
        <h2>Revisar un pedido recibido</h2>

        <div class="campo">
          <label for="pedido">Pedido recibido</label>
          <select id="pedido" v-model="revision.pedido_id">
            <option value="">Elija…</option>
            <option v-for="p in recibidos" :key="p.id" :value="p.id">
              {{ p.codigo }} — {{ p.paciente }}
            </option>
          </select>
        </div>

        <button type="button" :disabled="!revision.pedido_id" @click="comparar">
          Comparar lo pedido contra lo recibido
        </button>

        <template v-if="revision.diferencias.length || revision.pedido_id">
          <div v-if="revision.diferencias.length" class="aviso-error">
            <strong>Diferencias encontradas</strong>
            La comparación es exacta: si el valor no coincide, sale en la lista.
            Cuál se acepta lo decide la persona, no el sistema.
          </div>

          <table v-if="revision.diferencias.length" style="margin-top: 0.8rem">
            <thead>
              <tr>
                <th>Ojo</th>
                <th>Meridiano</th>
                <th>Campo</th>
                <th>Pedido</th>
                <th>Recibido</th>
                <th>Diferencia</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(d, i) in revision.diferencias" :key="i">
                <td>{{ d.ojo }}</td>
                <td>{{ d.meridiano ?? '—' }}</td>
                <td>{{ d.campo }}</td>
                <td>{{ d.pedido ?? '—' }}</td>
                <td>{{ d.recibido ?? '—' }}</td>
                <td>{{ d.diferencia ?? '—' }}</td>
              </tr>
            </tbody>
          </table>

          <div class="campo" style="margin-top: 1rem">
            <label for="resultado">Veredicto</label>
            <select id="resultado" v-model="revision.resultado">
              <option value="conforme">Conforme</option>
              <option value="con_diferencias">Con diferencias aceptadas</option>
              <option value="no_conforme">No conforme</option>
            </select>
            <p class="tenue">
              «No conforme» bloquea la entrega. Si lo eliges, deja escrito qué
              se encontró y qué se va a hacer.
            </p>
          </div>

          <div class="campo">
            <label for="obs">Observaciones</label>
            <textarea id="obs" v-model="revision.observaciones" rows="3" />
          </div>

          <button class="primario" type="button" :disabled="revision.guardando" @click="registrar">
            {{ revision.guardando ? 'Registrando…' : 'Registrar control' }}
          </button>
        </template>
      </div>

      <div class="marco">
        <h2>Controles registrados</h2>

        <table v-if="controles && controles.datos.length">
          <thead>
            <tr>
              <th>Pedido</th>
              <th>Fecha</th>
              <th>Resultado</th>
              <th>Diferencias</th>
              <th>Revisó</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in controles.datos" :key="c.id">
              <td>{{ c.pedido_codigo }}</td>
              <td>{{ c.fecha_revision }}</td>
              <td>
                <span :class="c.permite_entregar ? 'estado ok' : 'estado peligro'">
                  {{ c.resultado_etiqueta }}
                </span>
              </td>
              <td>{{ c.cantidad_diferencias }}</td>
              <td class="tenue">{{ c.revisado_por }}</td>
            </tr>
          </tbody>
        </table>

        <p v-else class="vacio">Todavía no hay controles de calidad.</p>
      </div>
    </template>

    <p v-else class="marco">
      El control de calidad compara graduaciones. Solo el optometrista puede
      hacerlo.
    </p>
  </div>
</template>