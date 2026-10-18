<script setup lang="ts">
import type { Paciente } from '~/tipos/api';

/**
 * Ficha de un paciente.
 *
 * LA GRADUACION SOLO APARECE SI LA API LA MANDA
 * ---------------------------------------------
 * El endpoint `GET /v1/pacientes/{id}` no incluye recetas ni graduaciones a
 * quien no tiene permiso. Esta pantalla no decide: si el bloque no aparece, es
 * porque la API no lo devolvio.
 *
 * Ver docs/07.
 */
definePageMeta({ middleware: 'auth' });

const ruta = useRoute();
const { puedeVerRecetas } = useSesion();

const paciente = ref<Paciente | null>(null);
const cargando = ref(true);
const error = ref<string | null>(null);

onMounted(async () => {
  try {
    paciente.value = await $fetch<Paciente>(`/api/v1/pacientes/${ruta.params.id}`);
  } catch {
    /*
     * Un 404 aca no significa que el paciente no exista: puede ser que exista
     * en otra optica. Por eso el mensaje no lo dice. Ver docs/07.
     */
    error.value = 'No se encontró la ficha, o no tiene permiso para verla.';
  } finally {
    cargando.value = false;
  }
});

function claseVencimiento(estado: string): string {
  if (estado === 'vencida') return 'peligro';
  if (estado === 'por_vencer') return 'aviso';
  if (estado === 'vigente') return 'ok';
  return 'neutro';
}

function etiquetaVencimiento(estado: string): string {
  if (estado === 'vigente') return 'Vigente';
  if (estado === 'por_vencer') return 'Por vencer';
  if (estado === 'vencida') return 'Vencida';
  if (estado === 'sin_plazo') return 'Sin plazo cargado';
  return 'No aplica';
}
</script>

<template>
  <div>
    <p><NuxtLink to="/pacientes">← Pacientes</NuxtLink></p>

    <div v-if="cargando" class="vacio">Cargando…</div>
    <div v-else-if="error" class="aviso-error">{{ error }}</div>

    <template v-else-if="paciente">
      <h1>{{ paciente.nombre }}</h1>

      <div class="marco">
        <div class="fila">
          <div>
            <strong>RUT</strong><br />
            {{ paciente.rut ?? '—' }}
          </div>
          <div>
            <strong>Fecha de nacimiento</strong><br />
            {{ paciente.fecha_nacimiento ?? '—' }}
            <span v-if="paciente.edad_anios !== null" class="tenue">
              ({{ paciente.edad_anios }} años)
            </span>
          </div>
          <div>
            <strong>Teléfono</strong><br />
            {{ paciente.telefono ?? '—' }}
          </div>
          <div>
            <strong>Correo</strong><br />
            {{ paciente.correo ?? '—' }}
          </div>
        </div>
      </div>

      <!--
          La última receta solo se muestra si la API la mandó, es decir, si la
          persona tiene permiso para ver graduaciones.
      -->
      <div v-if="puedeVerRecetas && paciente.ultima_receta" class="marco">
        <h2>Última receta</h2>

        <p>
          <NuxtLink :to="`/recetas/${paciente.ultima_receta.id}`">
            {{ paciente.ultima_receta.numero }}
          </NuxtLink>
          · emitida el {{ paciente.ultima_receta.fecha_emision }}
        </p>

        <p>
          <span :class="['estado', claseVencimiento(paciente.ultima_receta.vencimiento.estado)]">
            {{ etiquetaVencimiento(paciente.ultima_receta.vencimiento.estado) }}
          </span>
        </p>

        <!--
            El aviso aparece cuando el sistema NO sabe cuando vence. Es lo mas
            importante de esta pantalla: sin fecha, "sin plazo" no es lo mismo
            que "vigente".
        -->
        <div
          v-if="paciente.ultima_receta.vencimiento.pendiente_de_verificacion"
          class="pendiente"
        >
          <strong>Plazo pendiente de verificación</strong>
          {{ paciente.ultima_receta.vencimiento.explicacion }}
          <span v-if="paciente.ultima_receta.vencimiento.franja_etaria">
            Franja detectada: {{ paciente.ultima_receta.vencimiento.franja_etaria }}.
          </span>
        </div>

        <p v-if="paciente.ultima_receta.vencimiento.explicacion" class="tenue">
          {{ paciente.ultima_receta.vencimiento.explicacion }}
        </p>
      </div>

      <div v-else-if="!puedeVerRecetas" class="marco">
        <p class="tenue">
          Esta pantalla no muestra recetas. La graduación es información de salud
          y solo la ve el optometrista.
        </p>
      </div>
    </template>
  </div>
</template>