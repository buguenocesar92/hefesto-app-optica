<script setup lang="ts">
import type { Lente, Paginado } from '~/tipos/api';

/**
 * Catalogo de lentes.
 *
 * LOS DATOS INCOMPLETOS SE MARCAN, NO SE OCULTAN
 * ----------------------------------------------
 * `indice_refraccion` y `diametro_corte_mm` nacen en NULL porque no se pudo
 * verificar ningún valor contra las fichas de los fabricantes. Un lente sin
 * ellos se puede guardar, pero la pantalla lo avisa: es el mismo aviso que va
 * a bloquear el despacho si alguien elige esa combinación.
 *
 * Ver docs/06.
 */
definePageMeta({ middleware: 'auth' });

const pagina = ref<Paginado<Lente> | null>(null);
const cargando = ref(true);
const error = ref<string | null>(null);

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    pagina.value = await $fetch<Paginado<Lente>>('/api/v1/lentes');
  } catch {
    error.value = 'No se pudo cargar el catálogo de lentes.';
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);
</script>

<template>
  <div>
    <h1>Lentes</h1>

    <p class="tenue">
      El diámetro de corte es lo que permite saber si el lente entra en un
      armazón. Mientras un lente no lo tenga cargado, la verificación dirá
      «no verificable» en vez de adivinar.
    </p>

    <div v-if="error" class="aviso-error">{{ error }}</div>
    <div v-if="cargando" class="vacio">Cargando…</div>

    <table v-else-if="pagina && pagina.datos.length">
      <thead>
        <tr>
          <th>Código</th>
          <th>Nombre</th>
          <th>Material</th>
          <th>Índice</th>
          <th>Diámetro de corte</th>
          <th>Tratamientos</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="lente in pagina.datos" :key="lente.id">
          <td>{{ lente.codigo }}</td>
          <td>
            {{ lente.nombre }}
            <span v-if="lente.es_progresivo" class="tenue">(progresivo)</span>
          </td>
          <td>{{ lente.material ?? '—' }}</td>
          <td>
            <span v-if="lente.indice_refraccion === null" class="estado neutro">sin cargar</span>
            <span v-else>{{ lente.indice_refraccion }}</span>
          </td>
          <td>
            <span v-if="lente.diametro_corte_mm === null" class="estado neutro">sin cargar</span>
            <span v-else>{{ lente.diametro_corte_mm }} mm</span>
          </td>
          <td class="tenue">{{ lente.tratamientos.join(', ') || '—' }}</td>
          <td>
            <span :class="lente.activo ? 'estado ok' : 'estado neutro'">
              {{ lente.activo ? 'Activo' : 'Inactivo' }}
            </span>
            <br />
            <span v-if="lente.datos_incompletos.length" class="tenue">
              falta: {{ lente.datos_incompletos.join(', ') }}
            </span>
          </td>
        </tr>
      </tbody>
    </table>

    <p v-else class="vacio">No hay lentes en el catálogo.</p>
  </div>
</template>