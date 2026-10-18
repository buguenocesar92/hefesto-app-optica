<script setup lang="ts">
import type { Graduacion, Receta } from '~/tipos/api';

/**
 * Una receta y su graduación.
 *
 * LA GRADUACIÓN SE MUESTRA COMO CUATRO FILAS
 * ------------------------------------------
 * Dos ojos por dos meridianos. No como una tabla con columnas
 * `esfera_lejos`, `eje_cerca` y así: la estructura es la del dato, y la
 * pantalla la refleja tal cual.
 *
 * Ver docs/02.
 */
definePageMeta({ middleware: 'auth' });

const ruta = useRoute();

const receta = ref<Receta | null>(null);
const cargando = ref(true);
const error = ref<string | null>(null);

onMounted(async () => {
  try {
    receta.value = await $fetch<Receta>(`/api/v1/recetas/${ruta.params.id}`);
  } catch {
    error.value = 'No se encontró la receta, o no tiene permiso para verla.';
  } finally {
    cargando.value = false;
  }
});

function porOjo(ojo: 'derecho' | 'izquierdo'): Graduacion[] {
  return (receta.value?.graduaciones ?? []).filter((g) => g.ojo === ojo);
}

function clase(estado: string): string {
  if (estado === 'vencida') return 'peligro';
  if (estado === 'por_vencer') return 'aviso';
  if (estado === 'vigente') return 'ok';
  return 'neutro';
}
</script>

<template>
  <div>
    <p><NuxtLink to="/recetas">← Recetas</NuxtLink></p>

    <div v-if="cargando" class="vacio">Cargando…</div>
    <div v-else-if="error" class="aviso-error">{{ error }}</div>

    <template v-else-if="receta">
      <h1>Receta {{ receta.numero }}</h1>
      <p class="tenue">
        {{ receta.paciente }} · emitida el {{ receta.fecha_emision }} por
        {{ receta.optometrista }}
      </p>

      <div class="marco">
        <h2>Vigencia</h2>

        <p>
          <span :class="['estado', clase(receta.vencimiento.estado)]">
            {{ receta.vencimiento.estado }}
          </span>
          <span v-if="receta.fecha_vencimiento" class="tenue">
            · vence el {{ receta.fecha_vencimiento }}
          </span>
        </p>

        <!--
            Si el plazo no esta verificado, se dice. Es la diferencia entre
            "esta vigente" y "el sistema no sabe".
        -->
        <div v-if="receta.vencimiento.pendiente_de_verificacion" class="pendiente">
          <strong>Plazo legal pendiente de verificación</strong>
          {{ receta.vencimiento.explicacion }}
          <span v-if="receta.vencimiento.franja_etaria">
            Este paciente cae en: {{ receta.vencimiento.franja_etaria }}.
          </span>
          <span v-else>
            No se pudo determinar la franja etaria: el paciente no tiene fecha
            de nacimiento cargada.
          </span>
        </div>

        <div v-if="receta.motivo_sin_vencimiento" class="aviso-error">
          Emitida sin fecha de vencimiento. Motivo: {{ receta.motivo_sin_vencimiento }}
        </div>

        <p class="tenue">
          Distancia interpupilar:
          {{ receta.distancia_interpupilar_mm !== null ? `${receta.distancia_interpupilar_mm} mm` : 'sin cargar' }}
        </p>
      </div>

      <div class="marco">
        <h2>Graduación</h2>

        <table v-if="receta.graduaciones.length">
          <thead>
            <tr>
              <th>Ojo</th>
              <th>Meridiano</th>
              <th>Esfera</th>
              <th>Cilindro</th>
              <th>Eje</th>
              <th>Adición</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="g in receta.graduaciones" :key="`${g.ojo}-${g.meridiano}`">
              <td>{{ g.ojo_etiqueta }}</td>
              <td>{{ g.meridiano_etiqueta }}</td>
              <td>{{ g.esfera }}</td>
              <td>{{ g.cilindro }}</td>
              <td>{{ g.eje }}°</td>
              <td>{{ g.adicion ?? '—' }}</td>
            </tr>
          </tbody>
        </table>

        <p v-else class="vacio">
          Esta receta no tiene graduación cargada. Sin las cuatro filas no se
          puede despachar lentes.
        </p>

        <p v-if="receta.graduaciones.length" class="tenue">
          Cuatro filas: dos ojos por dos meridianos. La adición solo existe en el
          meridiano de cerca.
        </p>
      </div>

      <div v-if="receta.observaciones" class="marco">
        <h2>Observaciones</h2>
        <p>{{ receta.observaciones }}</p>
      </div>
    </template>
  </div>
</template>