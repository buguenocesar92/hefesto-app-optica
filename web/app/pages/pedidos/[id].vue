<script setup lang="ts">
import type { Pedido } from '~/tipos/api';

/**
 * Detalle de un pedido.
 *
 * LO QUE IMPORTA EN ESTA PANTALLA
 * -------------------------------
 *   1. La compatibilidad del armazón con el lente, tal como se calculó al
 *      crear el pedido, con el desglose.
 *   2. La graduación pedida, cuando quien mira tiene permiso.
 *   3. Si el pedido se puede entregar.
 *
 * LA GRADUACIÓN SE FILTRA EN EL SERVIDOR
 * -------------------------------------
 * El endpoint devuelve los renglones sin la graduación cuando quien pregunta
 * no puede leerla. Esta pantalla no oculta nada: no le llega.
 */
definePageMeta({ middleware: 'auth' });

const ruta = useRoute();

const pedido = ref<Pedido | null>(null);
const cargando = ref(true);
const error = ref<string | null>(null);

const tieneGraduaciones = computed(() =>
  (pedido.value?.items ?? []).some((item) => item.graduacion_pedida !== undefined),
);

onMounted(async () => {
  try {
    pedido.value = await $fetch<Pedido>(`/api/v1/pedidos/${ruta.params.id}`);
  } catch {
    error.value = 'No se encontró el pedido, o no tiene permiso para verlo.';
  } finally {
    cargando.value = false;
  }
});
</script>

<template>
  <div>
    <p><NuxtLink to="/pedidos">← Pedidos</NuxtLink></p>

    <div v-if="cargando" class="vacio">Cargando…</div>
    <div v-else-if="error" class="aviso-error">{{ error }}</div>

    <template v-else-if="pedido">
      <h1>Pedido {{ pedido.codigo }}</h1>
      <p class="tenue">
        {{ pedido.paciente }} · {{ pedido.laboratorio ?? 'laboratorio sin indicar' }}
      </p>

      <div class="marco">
        <h2>Estado</h2>
        <p><span class="estado neutro">{{ pedido.estado_etiqueta }}</span></p>

        <div class="fila">
          <div>
            <strong>Enviado</strong><br />
            {{ pedido.fecha_envio ?? '—' }}
          </div>
          <div>
            <strong>Prometido</strong><br />
            <span v-if="pedido.fecha_prometida">{{ pedido.fecha_prometida }}</span>
            <span v-else class="estado neutro">sin promesa</span>
          </div>
          <div>
            <strong>Recibido</strong><br />
            {{ pedido.fecha_recepcion ?? '—' }}
          </div>
        </div>

        <p v-if="!pedido.fecha_prometida" class="tenue">
          Sin fecha prometida no hay atraso que medir. El plazo lo negocia la
          óptica con su laboratorio; el sistema no propone ninguno.
        </p>

        <p>
          <span :class="pedido.puede_entregarse ? 'estado ok' : 'estado neutro'">
            {{ pedido.puede_entregarse ? 'Puede entregarse' : 'Falta el control de calidad' }}
          </span>
        </p>
      </div>

      <div class="marco">
        <h2>Compatibilidad armazón / lente</h2>

        <template v-if="pedido.compatibilidad.resultado">
          <p>
            <span
              :class="[
                'estado',
                pedido.compatibilidad.resultado === 'compatible' ? 'ok' :
                pedido.compatibilidad.resultado === 'incompatible' ? 'peligro' : 'neutro',
              ]"
            >
              {{ pedido.compatibilidad.etiqueta }}
            </span>
          </p>

          <div
            v-if="pedido.compatibilidad.resultado === 'no_verificable'"
            class="pendiente"
          >
            <strong>No verificable todavía</strong>
            Al momento de crear el pedido faltaba un dato del catálogo. La
            combinación se puede revisar a mano, pero el sistema no la da por
            buena.
          </div>

          <p v-if="pedido.notas" class="tenue">{{ pedido.notas }}</p>
        </template>

        <p v-else class="vacio">
          Este pedido se creó sin armazón ni lente, así que no hay compatibilidad
          que verificar.
        </p>
      </div>

      <div class="marco">
        <h2>Renglones</h2>

        <table v-if="pedido.items.length">
          <thead>
            <tr>
              <th>Ojo</th>
              <th>Cantidad</th>
              <th>Graduación recibida</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in pedido.items" :key="item.id">
              <td>{{ item.ojo_etiqueta }}</td>
              <td>{{ item.cantidad }}</td>
              <td>
                <span v-if="item.tiene_graduacion_recibida" class="estado ok">cargada</span>
                <span v-else class="estado neutro">falta</span>
              </td>
            </tr>
          </tbody>
        </table>

        <p v-else class="vacio">
          Este pedido no tiene renglones: se creó sin receta asociada.
        </p>
      </div>

      <!--
          Solo aparece si el servidor mandó la graduación. La receta guardada
          en el pedido es una FOTO de lo que salió de la óptica: si la receta
          se corrige después, el control de calidad sigue comparando contra lo
          que realmente se pidió.
      -->
      <div v-if="tieneGraduaciones" class="marco">
        <h2>Graduación pedida</h2>

        <table v-for="item in pedido.items" :key="`g-${item.id}`">
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
            <template v-for="meridiano in ['lejos', 'cerca']" :key="`${item.id}-${meridiano}`">
              <tr v-if="item.graduacion_pedida?.[meridiano]">
                <td>{{ item.ojo_etiqueta }}</td>
                <td>{{ meridiano === 'lejos' ? 'Lejos' : 'Cerca' }}</td>
                <td>{{ item.graduacion_pedida[meridiano].esfera }}</td>
                <td>{{ item.graduacion_pedida[meridiano].cilindro }}</td>
                <td>{{ item.graduacion_pedida[meridiano].eje }}°</td>
                <td>{{ item.graduacion_pedida[meridiano].adiccion ?? '—' }}</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <div v-if="pedido.estado === 'recibido' && !pedido.puede_entregarse" class="pendiente">
        <strong>Falta el control de calidad</strong>
        Este pedido llegó y todavía no se revisó. La entrega queda bloqueada
        hasta que el optometrista registre el control.
      </div>
    </template>
  </div>
</template>