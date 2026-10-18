<script setup lang="ts">
import type { Entrega, Paginado } from '~/tipos/api';

/**
 * Entregas al cliente.
 *
 * ENTREGAR NO ES EL FINAL
 * ----------------------
 * Un lente recién entregado casi nunca está bien ajustado la primera vez. Esta
 * pantalla muestra, junto a cada entrega, cuántos ajustes tiene abiertos: es
 * el número que dice si el trabajo quedó a medias.
 *
 * Ver docs/02.
 */
definePageMeta({ middleware: 'auth' });

const pagina = ref<Paginado<Entrega> | null>(null);
const cargando = ref(true);
const error = ref<string | null>(null);

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    pagina.value = await $fetch('/api/v1/entregas');
  } catch {
    error.value = 'No se pudo cargar el listado de entregas.';
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);
</script>

<template>
  <div>
    <h1>Entregas</h1>

    <p class="tenue">
      Todo lente se ajusta después de entregado. Una entrega con ajustes abiertos
      todavía no terminó.
    </p>

    <div v-if="error" class="aviso-error">{{ error }}</div>
    <div v-if="cargando" class="vacio">Cargando…</div>

    <table v-else-if="pagina && pagina.datos.length">
      <thead>
        <tr>
          <th>Pedido</th>
          <th>Paciente</th>
          <th>Estado</th>
          <th>Entregado</th>
          <th>Recibió</th>
          <th>Ajustes</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="entrega in pagina.datos" :key="entrega.id">
          <td>{{ entrega.pedido_codigo }}</td>
          <td>{{ entrega.paciente }}</td>
          <td><span class="estado neutro">{{ entrega.estado_etiqueta }}</span></td>
          <td>
            <span v-if="entrega.fecha_entrega">
              {{ entrega.fecha_entrega }}
              <span class="tenue">(hace {{ entrega.dias_desde_entrega }} d)</span>
            </span>
            <span v-else class="estado neutro">pendiente</span>
          </td>
          <td class="tenue">{{ entrega.recibido_por ?? '—' }}</td>
          <td>
            <span v-if="entrega.ajustes_abiertos > 0" class="estado aviso">
              {{ entrega.ajustes_abiertos }} abierto(s)
            </span>
            <span v-else class="estado ok">sin pendientes</span>
            <br />
            <span class="tenue">{{ entrega.ajustes.length }} registrado(s)</span>
          </td>
        </tr>
      </tbody>
    </table>

    <p v-else class="vacio">No hay entregas registradas.</p>
  </div>
</template>