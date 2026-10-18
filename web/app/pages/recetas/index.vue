<script setup lang="ts">
import type { Paginado, Receta } from '~/tipos/api';

/**
 * Listado de recetas.
 *
 * PAGINA QUE SOLO SE ABRE PARA EL OPTOMETRISTA
 * -------------------------------------------
 * La ruta no tiene `middleware` propio para el rol: el `middleware: 'auth'` de
 * la interfaz manda a la pantalla de entrada, y si alguien llega aqui sin
 * permiso, la API responde 404 y esta pantalla lo muestra como "no encontrada".
 *
 * Es a proposito: un 403 confirmaria que existen recetas. Ver docs/07.
 */
definePageMeta({ middleware: 'auth' });

const filtro = ref<'' | 'vencidas' | 'sin_plazo'>('');
const pagina = ref<Paginado<Receta> | null>(null);
const cargando = ref(true);
const error = ref<string | null>(null);

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    let consulta = '';

    if (filtro.value === 'vencidas') consulta = '?solo_vencidas=1';
    if (filtro.value === 'sin_plazo') consulta = '?solo_sin_plazo=1';

    pagina.value = await $fetch<Paginado<Receta>>(`/api/v1/recetas${consulta}`);
  } catch {
    error.value = 'No se pudo cargar el listado de recetas.';
  } finally {
    cargando.value = false;
  }
}

function clase(estado: string): string {
  if (estado === 'vencida') return 'peligro';
  if (estado === 'por_vencer') return 'aviso';
  if (estado === 'vigente') return 'ok';
  return 'neutro';
}

function etiqueta(estado: string): string {
  if (estado === 'vigente') return 'Vigente';
  if (estado === 'por_vencer') return 'Por vencer';
  if (estado === 'vencida') return 'Vencida';
  if (estado === 'sin_plazo') return 'Sin plazo';
  return estado;
}

onMounted(cargar);
</script>

<template>
  <div>
    <h1>Recetas</h1>

    <div class="fila" style="margin-bottom: 1rem; align-items: flex-end">
      <div>
        <label for="filtro">Filtrar</label>
        <select id="filtro" v-model="filtro" @change="cargar">
          <option value="">Todas</option>
          <option value="vencidas">Vencidas</option>
          <option value="sin_plazo">Sin fecha de vencimiento</option>
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
          <th>Número</th>
          <th>Paciente</th>
          <th>Emitida</th>
          <th>Vencimiento</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="receta in pagina.datos" :key="receta.id">
          <td>
            <NuxtLink :to="`/recetas/${receta.id}`">{{ receta.numero }}</NuxtLink>
          </td>
          <td>{{ receta.paciente }}</td>
          <td>{{ receta.fecha_emision }}</td>
          <td>
            <span :class="['estado', clase(receta.vencimiento.estado)]">
              {{ etiqueta(receta.vencimiento.estado) }}
            </span>
            <span v-if="receta.vencimiento.dias_restantes !== null" class="tenue">
              ({{ receta.vencimiento.dias_restantes }} d)
            </span>
          </td>
          <td class="tenue">{{ receta.estado_etiqueta }}</td>
        </tr>
      </tbody>
    </table>

    <p v-else class="vacio">No hay recetas para este filtro.</p>
  </div>
</template>