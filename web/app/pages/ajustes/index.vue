<script setup lang="ts">
import type { Ajuste, Entrega, Paginado } from '~/tipos/api';

/**
 * Ajustes posteriores a la entrega.
 *
 * EL MOTIVO ES EL DATO MAS UTIL
 * -----------------------------
 * Más que el resultado. Si casi todos los ajustes son por lo mismo, el
 * problema es del armazón que se eligió, no del lente. Por eso la pantalla
 * destaca el motivo más frecuente.
 *
 * NO HAY PLAZO DE GARANTÍA QUE CUMPLIR
 * -----------------------------------
 * El ajuste se registra en cualquier momento. Los días de garantía sugeridos
 * nacen en `null` porque son una decisión comercial, no un plazo legal
 * verificable. Ver docs/07.
 */
definePageMeta({ middleware: 'auth' });

const ajustes = ref<Paginado<Ajuste> | null>(null);
const entregas = ref<Entrega[]>([]);
const cargando = ref(true);
const error = ref<string | null>(null);

const motivoMasFrecuente = ref<string | null>(null);

const formulario = reactive({
  visible: false,
  entrega_id: '',
  fecha: new Date().toISOString().slice(0, 10),
  motivo: '',
  descripcion: '',
  lente_sustituido: false,
});

const MOTIVOS_SUGERIDOS = [
  'El armazón aprieta',
  'El armazón se desliza',
  'Los lentes quedan muy altos',
  'Los lentes quedan muy bajos',
  'Se nota el aro del lente',
  'Distorsión en la periferia',
  'Molestia al mirar de cerca',
  'Molestia al mirar de lejos',
];

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    const respuesta = await $fetch<{
      datos: Ajuste[];
      motivo_mas_frecuente: string | null;
      motivos_sugeridos: string[];
    }>('/api/v1/ajustes');

    ajustes.value = {
      datos: respuesta.datos,
      paginacion: { pagina: 1, por_pagina: respuesta.datos.length, total: respuesta.datos.length },
    };

    motivoMasFrecuente.value = respuesta.motivo_mas_frecuente;

    // Solo se ofrecen las entregas YA hechas: ajustar antes de entregar no
    // tiene sentido.
    entregas.value = (
      await $fetch<{ datos: Entrega[] }>('/api/v1/entregas').catch(() => null)
    )?.datos.filter((e) => e.admite_ajuste) ?? [];
  } catch {
    error.value = 'No se pudo cargar el listado de ajustes.';
  } finally {
    cargando.value = false;
  }
}

async function guardar(): Promise<void> {
  error.value = null;

  try {
    await $fetch('/api/v1/ajustes', {
      method: 'POST',
      body: {
        entrega_id: formulario.entrega_id,
        fecha: formulario.fecha,
        motivo: formulario.motivo,
        descripcion: formulario.descripcion || null,
        lente_sustituido: formulario.lente_sustituido,
      },
    });

    formulario.visible = false;
    formulario.motivo = '';
    formulario.descripcion = '';

    await cargar();
  } catch (e: any) {
    error.value = e?.data?.message ?? 'No se pudo registrar el ajuste.';
  }
}

onMounted(cargar);
</script>

<template>
  <div>
    <h1>Ajustes</h1>

    <div style="margin-bottom: 1rem">
      <button class="primario" type="button" @click="formulario.visible = true">
        Registrar ajuste
      </button>
    </div>

    <div v-if="motivoMasFrecuente" class="marco">
      <h2>Motivo más frecuente</h2>
      <p>
        <strong>{{ motivoMasFrecuente }}</strong>
      </p>
      <p class="tenue">
        Si se repite, el problema suele estar en el armazón que se eligió, no en
        el lente.
      </p>
    </div>

    <div v-if="error" class="aviso-error">{{ error }}</div>
    <div v-if="cargando" class="vacio">Cargando…</div>

    <table v-else-if="ajustes && ajustes.datos.length">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Paciente</th>
          <th>Motivo</th>
          <th>Estado</th>
          <th>Lente</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="ajuste in ajustes.datos" :key="ajuste.id">
          <td>{{ ajuste.fecha }}</td>
          <td>{{ ajuste.paciente ?? '—' }}</td>
          <td>
            {{ ajuste.motivo }}
            <br />
            <span v-if="ajuste.descripcion" class="tenue">{{ ajuste.descripcion }}</span>
          </td>
          <td>
            <span
              :class="ajuste.estado === 'pendiente' ? 'estado aviso' : 'estado ok'"
            >
              {{ ajuste.estado_etiqueta }}
            </span>
          </td>
          <td class="tenue">{{ ajuste.lente_sustituido ? 'sustituido' : 'se mantiene' }}</td>
        </tr>
      </tbody>
    </table>

    <p v-else class="vacio">No hay ajustes registrados.</p>

    <div v-if="formulario.visible" class="marco">
      <h2>Nuevo ajuste</h2>

      <div class="campo">
        <label for="entrega">Entrega</label>
        <select id="entrega" v-model="formulario.entrega_id">
          <option value="">Elija…</option>
          <option v-for="e in entregas" :key="e.id" :value="e.id">
            {{ e.pedido_codigo }} — {{ e.paciente }}
          </option>
        </select>
        <p v-if="entregas.length === 0" class="tenue">
          No hay entregas ya hechas. Un ajuste se registra después de entregar.
        </p>
      </div>

      <div class="campo">
        <label for="fecha">Fecha</label>
        <input id="fecha" v-model="formulario.fecha" type="date" />
      </div>

      <div class="campo">
        <label for="motivo">Motivo</label>
        <input id="motivo" v-model="formulario.motivo" list="motivos" type="text" />
        <datalist id="motivos">
          <option v-for="m in MOTIVOS_SUGERIDOS" :key="m" :value="m" />
        </datalist>
        <p class="tenue">
          Las sugerencias no son una lista cerrada: el campo acepta cualquier
          texto.
        </p>
      </div>

      <div class="campo">
        <label for="descripcion">Descripción</label>
        <textarea id="descripcion" v-model="formulario.descripcion" rows="2" />
      </div>

      <div class="campo">
        <label>
          <input v-model="formulario.lente_sustituido" type="checkbox" />
          Hubo que cambiar el lente
        </label>
      </div>

      <button class="primario" type="button" @click="guardar">Registrar</button>
      <button type="button" style="margin-left: 0.5rem" @click="formulario.visible = false">
        Cancelar
      </button>
    </div>
  </div>
</template>