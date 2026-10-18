<script setup lang="ts">
import type { Armazon, Lente, Paginado, ResultadoCompatibilidad } from '~/tipos/api';

/**
 * Catalogo de armazones, con la verificacion de compatibilidad a la vista.
 *
 * EL CALCULO SE MUESTRA ENTERO
 * ---------------------------
 * Cuando la verificacion devuelve `incompatible` o `no_verificable`, lo que
 * hace falta es entender que numero salio mal. Por eso esta pantalla muestra
 * el desglose completo: medidas de caja, margen, diametro requerido, holgura.
 *
 * Ver docs/06.
 */
definePageMeta({ middleware: 'auth' });

const { puedeAdministrar } = useSesion();

const pagina = ref<Paginado<Armazon> | null>(null);
const lentes = ref<Lente[]>([]);
const cargando = ref(true);
const error = ref<string | null>(null);

const verificacion = reactive({
  visible: false,
  armazon_id: '',
  lente_id: '',
  cargando: false,
  resultado: null as ResultadoCompatibilidad | null,
});

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    pagina.value = await $fetch<Paginado<Armazon>>('/api/v1/armazones');

    // Los lentes se necesitan para el selector de la verificación. Si esta
    // llamada falla, la verificación simplemente no se podrá hacer.
    lentes.value = (await $fetch<Paginado<Lente>>('/api/v1/lentes').catch(() => null))?.datos ?? [];
  } catch {
    error.value = 'No se pudo cargar el catálogo de armazones.';
  } finally {
    cargando.value = false;
  }
}

async function verificar(): Promise<void> {
  if (!verificacion.armazon_id || !verificacion.lente_id) {
    error.value = 'Elija un armazón y un lente.';
    return;
  }

  verificacion.cargando = true;
  error.value = null;

  try {
    verificacion.resultado = await $fetch<ResultadoCompatibilidad>('/api/v1/compatibilidad', {
      method: 'POST',
      body: { armazon_id: verificacion.armazon_id, lente_id: verificacion.lente_id },
    });
  } catch {
    error.value = 'No se pudo verificar la compatibilidad.';
  } finally {
    verificacion.cargando = false;
  }
}

function clase(veredicto: string): string {
  if (veredicto === 'compatible') return 'ok';
  if (veredicto === 'incompatible') return 'peligro';
  return 'neutro';
}

onMounted(cargar);
</script>

<template>
  <div>
    <h1>Armazones</h1>

    <p class="tenue">
      Las medidas de caja son lo que define si un lente entra. El ancho de caja es
      el ancho de un lente más el puente.
    </p>

    <div v-if="error" class="aviso-error">{{ error }}</div>
    <div v-if="cargando" class="vacio">Cargando…</div>

    <table v-else-if="pagina && pagina.datos.length">
      <thead>
        <tr>
          <th>Código</th>
          <th>Nombre</th>
          <th>Marca</th>
          <th>Material</th>
          <th>Medidas de caja (mm)</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="armazon in pagina.datos" :key="armazon.id">
          <td>{{ armazon.codigo }}</td>
          <td>{{ armazon.nombre }}</td>
          <td>{{ armazon.marca ?? '—' }}</td>
          <td>{{ armazon.material ?? '—' }}</td>
          <td>
            {{ armazon.medidas_legibles.ancho_varilla_mm }} —
            {{ armazon.medidas_legibles.puente_mm }} —
            {{ armazon.medidas_legibles.alto_mm }}
            <br />
            <span class="tenue">
              caja: {{ armazon.medidas_legibles.ancho_de_caja_mm }} mm de ancho
            </span>
          </td>
          <td>
            <span :class="armazon.activo ? 'estado ok' : 'estado neutro'">
              {{ armazon.activo ? 'Activo' : 'Inactivo' }}
            </span>
          </td>
        </tr>
      </tbody>
    </table>

    <p v-else class="vacio">No hay armazones en el catálogo.</p>

    <div class="marco" style="margin-top: 1.5rem">
      <h2>Verificar si un armazón y un lente encajan</h2>

      <p class="tenue">
        La verificación la hace el servidor con las medidas del catálogo. Si falta
        un dato, dirá que no se puede verificar en vez de adivinar.
      </p>

      <div class="fila">
        <div class="campo">
          <label for="armazon">Armazón</label>
          <select id="armazon" v-model="verificacion.armazon_id">
            <option value="">Elija…</option>
            <option v-for="a in pagina?.datos ?? []" :key="a.id" :value="a.id">
              {{ a.codigo }} — {{ a.nombre }} ({{ a.medidas_legibles.ancho_de_caja_mm }} mm)
            </option>
          </select>
        </div>
        <div class="campo">
          <label for="lente">Lente</label>
          <select id="lente" v-model="verificacion.lente_id">
            <option value="">Elija…</option>
            <option v-for="l in lentes" :key="l.id" :value="l.id">
              {{ l.codigo }} — {{ l.nombre }}
              <template v-if="l.diametro_corte_mm === null">(sin diámetro de corte)</template>
            </option>
          </select>
          <p v-if="lentes.length === 0" class="tenue" style="margin-top: 0.3rem">
            No hay lentes en el catálogo. La verificación necesita al menos uno.
          </p>
        </div>
      </div>

      <button class="primario" type="button" :disabled="verificacion.cargando" @click="verificar">
        {{ verificacion.cargando ? 'Verificando…' : 'Verificar' }}
      </button>

      <template v-if="verificacion.resultado">
        <p style="margin-top: 1rem">
          <span :class="['estado', clase(verificacion.resultado.veredicto)]">
            {{ verificacion.resultado.veredicto_etiqueta }}
          </span>
        </p>

        <p v-if="verificacion.resultado.motivo">{{ verificacion.resultado.motivo }}</p>

        <div
          v-if="verificacion.resultado.datos_faltantes.length"
          class="pendiente"
        >
          <strong>Faltan datos para poder afirmar nada</strong>
          {{ verificacion.resultado.datos_faltantes.join(', ') }}.
          Con esos datos en blanco el sistema no puede decir que el lente entre.
        </div>

        <!--
            El desglose va completo. Cuando el veredicto es "no compatible" o
            "no verificable", el número que manda está en esta tabla.
        -->
        <table v-if="verificacion.resultado.desglose" style="margin-top: 0.8rem">
          <thead>
            <tr>
              <th>Magnitud</th>
              <th>Valor (mm)</th>
              <th>De dónde sale</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Ancho de caja</td>
              <td>{{ verificacion.resultado.desglose.ancho_de_caja_mm }}</td>
              <td class="tenue">ancho de varilla + puente</td>
            </tr>
            <tr>
              <td>Alto de caja</td>
              <td>{{ verificacion.resultado.desglose.alto_de_caja_mm }}</td>
              <td class="tenue">alto del armazón</td>
            </tr>
            <tr>
              <td>Margen efectivo mínimo</td>
              <td>
                {{
                  verificacion.resultado.desglose.margen_efectivo_minimo_mm === null ||
                  verificacion.resultado.desglose.margen_efectivo_minimo_mm === undefined
                    ? 'pendiente de verificación'
                    : verificacion.resultado.desglose.margen_efectivo_minimo_mm
                }}
              </td>
              <td class="tenue">configuración, por lado</td>
            </tr>
            <tr>
              <td>Diámetro requerido (ancho)</td>
              <td>{{ verificacion.resultado.desglose.diametro_horizontal_requerido_mm ?? '—' }}</td>
              <td class="tenue">ancho de caja + 2 × margen</td>
            </tr>
            <tr>
              <td>Diámetro requerido (alto)</td>
              <td>{{ verificacion.resultado.desglose.diametro_vertical_requerido_mm ?? '—' }}</td>
              <td class="tenue">alto de caja + 2 × margen</td>
            </tr>
            <tr>
              <td><strong>Diámetro mínimo requerido</strong></td>
              <td><strong>{{ verificacion.resultado.desglose.diametro_minimo_requerido_mm ?? '—' }}</strong></td>
              <td class="tenue">
                manda el eje {{ verificacion.resultado.desglose.eje_que_manda ?? '—' }}
              </td>
            </tr>
            <tr>
              <td>Diámetro de corte del lente</td>
              <td>{{ verificacion.resultado.desglose.lente?.diametro_corte_mm ?? 'sin cargar' }}</td>
              <td class="tenue">catálogo de lentes</td>
            </tr>
            <tr>
              <td><strong>Holgura</strong></td>
              <td><strong>{{ verificacion.resultado.desglose.holgura_mm ?? '—' }}</strong></td>
              <td class="tenue">diámetro del lente − diámetro requerido</td>
            </tr>
          </tbody>
        </table>
      </template>
    </div>
  </div>
</template>