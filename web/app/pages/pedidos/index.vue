<script setup lang="ts">
import type { Paginado, Pedido } from '~/tipos/api';

/**
 * Pedidos a laboratorio.
 *
 * LA FECHA PROMETIDA SE MUESTRA O NO SE MUESTRA
 * --------------------------------------------
 * Si el pedido no tiene promesa, la columna dice «sin promesa». No dice un
 * plazo por omisión: el plazo es comercial y lo negocia la optica con su
 * laboratorio, no el software.
 *
 * Ver docs/06.
 */
definePageMeta({ middleware: 'auth' });

const filtro = ref<'en_curso' | 'recibidos' | ''>('en_curso');
const pagina = ref<Paginado<Pedido> | null>(null);
const cargando = ref(true);
const error = ref<string | null>(null);

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    let consulta = '';

    if (filtro.value === 'en_curso') consulta = '?en_curso=1';
    if (filtro.value === 'recibidos') consulta = '?estado=recibido';

    pagina.value = await $fetch<Paginado<Pedido>>(`/api/v1/pedidos${consulta}`);
  } catch {
    error.value = 'No se pudo cargar el listado de pedidos.';
  } finally {
    cargando.value = false;
  }
}

function claseCompatibilidad(veredicto: string | null): string {
  if (veredicto === 'compatible') return 'ok';
  if (veredicto === 'incompatible') return 'peligro';
  return 'neutro';
}

onMounted(cargar);
</script>

<template>
  <div>
    <h1>Pedidos a laboratorio</h1>

    <p class="tenue">
      La óptica no fabrica: encarga. La fecha prometida la negocia la óptica con
      su laboratorio, así que puede quedar en blanco.
    </p>

    <div class="fila" style="margin-bottom: 1rem; align-items: flex-end">
      <div>
        <label for="filtro">Estado</label>
        <select id="filtro" v-model="filtro" @change="cargar">
          <option value="en_curso">En curso</option>
          <option value="recibidos">Recibidos</option>
          <option value="">Todos</option>
        </select>
      </div>
      <div style="flex: 0 0 auto">
        <button type="button" @click="cargar">Actualizar</button>
      </div>
    </div>

    <div v-if="error" class="aviso-error">{{ error }}</div>
    <div v-if="cargando" class="vacio">Cargando…</div>

    <table v-else-if="pagina && pagina.datos.length">
      <thead>
        <tr>
          <th>Código</th>
          <th>Paciente</th>
          <th>Estado</th>
          <th>Prometido</th>
          <th>Compatibilidad</th>
          <th>Entregable</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="pedido in pagina.datos" :key="pedido.id">
          <td>
            <NuxtLink :to="`/pedidos/${pedido.id}`">{{ pedido.codigo }}</NuxtLink>
          </td>
          <td>{{ pedido.paciente }}</td>
          <td><span class="estado neutro">{{ pedido.estado_etiqueta }}</span></td>
          <td>
            <span v-if="!pedido.fecha_prometida" class="estado neutro">sin promesa</span>
            <span v-else :class="pedido.esta_atrasado ? 'estado peligro' : 'tenue'">
              {{ pedido.fecha_prometida }}
              <template v-if="pedido.esta_atrasado"> · atrasado</template>
            </span>
          </td>
          <td>
            <span
              v-if="pedido.compatibilidad.resultado"
              :class="['estado', claseCompatibilidad(pedido.compatibilidad.resultado)]"
            >
              {{ pedido.compatibilidad.etiqueta }}
            </span>
            <span v-else class="tenue">—</span>
          </td>
          <td>
            <span :class="pedido.puede_entregarse ? 'estado ok' : 'estado neutro'">
              {{ pedido.puede_entregarse ? 'Sí' : 'Falta control de calidad' }}
            </span>
          </td>
        </tr>
      </tbody>
    </table>

    <p v-else class="vacio">No hay pedidos para este filtro.</p>
  </div>
</template>