<script setup lang="ts">
import type { Paciente, Paginado } from '~/tipos/api';

/**
 * Listado de pacientes.
 *
 * LO QUE NO SE MUESTRA Y POR QUE
 * ------------------------------
 * Este listado no tiene columna de graduación, y no es una decisión de diseño:
 * la API no la manda. La ficha es dato de contacto; la graduación es
 * información de salud y vive en `recetas`, con su propia policy.
 *
 * Ver docs/07.
 */
definePageMeta({ middleware: 'auth' });

const busqueda = ref('');
const pagina = ref<Paginado<Paciente> | null>(null);
const cargando = ref(true);
const error = ref<string | null>(null);

const formulario = reactive({
  visible: false,
  nombres: '',
  apellidos: '',
  rut: '',
  fecha_nacimiento: '',
  telefono: '',
  correo: '',
});

const guardando = ref(false);

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    const consulta = busqueda.value.trim()
      ? `?q=${encodeURIComponent(busqueda.value.trim())}`
      : '';

    pagina.value = await $fetch<Paginado<Paciente>>(`/api/v1/pacientes${consulta}`);
  } catch {
    error.value = 'No se pudo cargar el listado de pacientes.';
  } finally {
    cargando.value = false;
  }
}

async function guardar(): Promise<void> {
  guardando.value = true;
  error.value = null;

  try {
    await $fetch('/api/v1/pacientes', {
      method: 'POST',
      body: {
        nombres: formulario.nombres,
        apellidos: formulario.apellidos,
        rut: formulario.rut || null,
        fecha_nacimiento: formulario.fecha_nacimiento || null,
        telefono: formulario.telefono || null,
        correo: formulario.correo || null,
      },
    });

    formulario.visible = false;
    formulario.nombres = '';
    formulario.apellidos = '';
    formulario.rut = '';

    await cargar();
  } catch (e: any) {
    error.value = e?.data?.message ?? 'No se pudo guardar el paciente.';
  } finally {
    guardando.value = false;
  }
}

onMounted(cargar);
</script>

<template>
  <div>
    <h1>Pacientes</h1>

    <div class="fila" style="margin-bottom: 1rem; align-items: flex-end">
      <div>
        <label for="q">Buscar</label>
        <input id="q" v-model="busqueda" type="search" placeholder="Nombre, apellido o RUT" />
      </div>
      <div style="flex: 0 0 auto">
        <button type="button" @click="cargar">Buscar</button>
      </div>
      <div style="flex: 0 0 auto">
        <button class="primario" type="button" @click="formulario.visible = true">
          Nuevo paciente
        </button>
      </div>
    </div>

    <div v-if="error" class="aviso-error">{{ error }}</div>

    <div v-if="cargando" class="vacio">Cargando…</div>

    <table v-else-if="pagina && pagina.datos.length">
      <thead>
        <tr>
          <th>RUT</th>
          <th>Nombre</th>
          <th>Teléfono</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="paciente in pagina.datos" :key="paciente.id">
          <td>{{ paciente.rut ?? '—' }}</td>
          <td>
            <NuxtLink :to="`/pacientes/${paciente.id}`">{{ paciente.nombre }}</NuxtLink>
          </td>
          <td>{{ paciente.telefono ?? '—' }}</td>
          <td>
            <span :class="paciente.activo ? 'estado ok' : 'estado neutro'">
              {{ paciente.activo ? 'Activo' : 'Inactivo' }}
            </span>
          </td>
        </tr>
      </tbody>
    </table>

    <p v-else class="vacio">No hay pacientes que coincidan.</p>

    <p v-if="pagina" class="tenue">
      {{ pagina.paginacion.total }} en total.
    </p>

    <div v-if="formulario.visible" class="marco">
      <h2>Nuevo paciente</h2>

      <div class="fila">
        <div class="campo">
          <label for="nombres">Nombres</label>
          <input id="nombres" v-model="formulario.nombres" type="text" />
        </div>
        <div class="campo">
          <label for="apellidos">Apellidos</label>
          <input id="apellidos" v-model="formulario.apellidos" type="text" />
        </div>
        <div class="campo">
          <label for="rut">RUT</label>
          <input id="rut" v-model="formulario.rut" type="text" placeholder="12.345.678-9" />
        </div>
      </div>

      <div class="fila">
        <div class="campo">
          <label for="fn">Fecha de nacimiento</label>
          <input id="fn" v-model="formulario.fecha_nacimiento" type="date" />
          <p class="tenue">
            Decide la franja de vigencia de la receta. Si se deja vacío, el
            sistema avisará que no puede calcular el plazo.
          </p>
        </div>
        <div class="campo">
          <label for="tel">Teléfono</label>
          <input id="tel" v-model="formulario.telefono" type="tel" />
        </div>
        <div class="campo">
          <label for="cor">Correo</label>
          <input id="cor" v-model="formulario.correo" type="email" />
        </div>
      </div>

      <button class="primario" type="button" :disabled="guardando" @click="guardar">
        {{ guardando ? 'Guardando…' : 'Guardar' }}
      </button>
      <button type="button" style="margin-left: 0.5rem" @click="formulario.visible = false">
        Cancelar
      </button>
    </div>
  </div>
</template>