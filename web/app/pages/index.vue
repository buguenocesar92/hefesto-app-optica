<script setup lang="ts">
import type { Pedido, ResumenRecetas } from '~/tipos/api';

/**
 * Portada de la optica.
 *
 * QUE MUESTRA Y POR QUE
 * ---------------------
 * Las tres cosas con las que se entra al dia:
 *
 *   1. Recetas vencidas y por vencer. Con el paciente y el numero, que es lo
 *      que hace falta para llamar.
 *   2. Pedidos atrasados contra la fecha prometida.
 *   3. El estado del dato pendiente de verificacion.
 *
 * EL BLOQUE "PENDIENTE" NO ES UN ADORNO
 * -------------------------------------
 * Si el plazo legal por franja etaria no se ha verificado contra la norma
 * vigente, el sistema no puede decir si una receta esta vigente. Eso va
 * escrito, arriba, siempre. Un sistema que dice "0 vencidas" cuando en realidad
 * no sabe, esta mintiendo con la mejor de las intenciones.
 *
 * Ver docs/06.
 */
definePageMeta({ middleware: 'auth' });

const { usuario, puedeOperar, puedeVerRecetas } = useSesion();

const recetas = ref<ResumenRecetas | null>(null);
const pedidos = ref<Pedido[]>([]);
const cargando = ref(true);
const error = ref<string | null>(null);

onMounted(async () => {
  try {
    const avisos = await $fetch<ResumenRecetas>('/api/v1/recetas-vencidas/indice').catch(
      () => null,
    );

    recetas.value = avisos;

    if (puedeOperar.value) {
      const respuesta = await $fetch<{ datos: Pedido[] }>('/api/v1/pedidos?en_curso=1&por_pagina=10');
      pedidos.value = respuesta.datos;
    }
  } catch {
    error.value = 'No se pudo cargar el resumen.';
  } finally {
    cargando.value = false;
  }
});

function claseEstado(estado: string): string {
  if (estado === 'vencida') return 'peligro';
  if (estado === 'por_vencer') return 'aviso';
  if (estado === 'vigente') return 'ok';
  return 'neutro';
}
</script>

<template>
  <div>
    <h1>{{ usuario?.optica?.nombre }}</h1>
    <p class="tenue">Sesión de {{ usuario?.nombre }} ({{ usuario?.rol_etiqueta }}).</p>

    <div v-if="cargando" class="vacio">Cargando…</div>
    <div v-else-if="error" class="aviso-error">{{ error }}</div>

    <template v-else>
      <!--
          El aviso de dato pendiente va PRIMERO, no al pie. Si el sistema no
          sabe cuando vence una receta, eso cambia como se lee todo lo demas.
      -->
      <div v-if="recetas?.plazo_legal_pendiente_de_verificacion" class="pendiente">
        <strong>Plazo de vigencia de las recetas pendiente de verificación</strong>
        El plazo por franja etaria es norma sanitaria chilena y en esta instalación
        todavía no se ha verificado contra la norma vigente. Por eso las recetas
        nacen sin fecha de vencimiento, el sistema no vence nada por su cuenta y
        la cuenta de recetas sin plazo no es cero.
        Ver <code>docs/06-PRIMERA-RECETA.md</code>.
      </div>

      <div v-if="puedeVerRecetas" class="marco">
        <h2>Recetas</h2>

        <table v-if="recetas">
          <thead>
            <tr>
              <th>Estado</th>
              <th>Cantidad</th>
              <th>Qué significa</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><span class="estado ok">Vigentes</span></td>
              <td>{{ recetas.vigentes }}</td>
              <td class="tenue">Tienen fecha de vencimiento y todavía no llega.</td>
            </tr>
            <tr>
              <td><span class="estado aviso">Por vencer</span></td>
              <td>{{ recetas.por_vencer }}</td>
              <td class="tenue">Vencen dentro de {{ recetas.dias_de_aviso }} días.</td>
            </tr>
            <tr>
              <td><span class="estado peligro">Vencidas</span></td>
              <td>{{ recetas.vencidas }}</td>
              <td class="tenue">Ya pasaron su fecha. No corresponde despachar sobre ellas.</td>
            </tr>
            <tr>
              <td><span class="estado neutro">Sin plazo</span></td>
              <td>{{ recetas.sin_plazo }}</td>
              <td class="tenue">
                El sistema no puede decir si están vigentes. Requieren una fecha
                cargada a mano.
              </td>
            </tr>
          </tbody>
        </table>

        <p v-if="recetas && recetas.franjas.length" class="tenue">
          Franjas configuradas, con su plazo cuando existe:
        </p>

        <ul v-if="recetas" class="tenue" style="margin: 0.3rem 0; padding-left: 1.2rem">
          <li v-for="franja in recetas.franjas" :key="franja.etiqueta">
            {{ franja.etiqueta }}:
            <strong v-if="franja.dias === null">plazo pendiente de verificación</strong>
            <strong v-else>{{ franja.dias }} días</strong>
          </li>
        </ul>
      </div>

      <div v-if="puedeOperar" class="marco">
        <h2>Pedidos en curso</h2>

        <table v-if="pedidos.length">
          <thead>
            <tr>
              <th>Código</th>
              <th>Paciente</th>
              <th>Estado</th>
              <th>Prometido</th>
              <th>Compatibilidad</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="pedido in pedidos" :key="pedido.id">
              <td><NuxtLink :to="`/pedidos/${pedido.id}`">{{ pedido.codigo }}</NuxtLink></td>
              <td>{{ pedido.paciente }}</td>
              <td>
                <span class="estado neutro">{{ pedido.estado_etiqueta }}</span>
              </td>
              <td>
                <span v-if="!pedido.fecha_prometida" class="tenue">sin promesa</span>
                <span v-else :class="pedido.esta_atrasado ? 'estado peligro' : 'tenue'">
                  {{ pedido.fecha_prometida }}
                  <template v-if="pedido.esta_atrasado"> · atrasado</template>
                </span>
              </td>
              <td>
                <span
                  v-if="pedido.compatibilidad.resultado"
                  :class="claseEstado(pedido.compatibilidad.resultado)"
                  class="estado"
                >
                  {{ pedido.compatibilidad.etiqueta }}
                </span>
                <span v-else class="tenue">—</span>
              </td>
            </tr>
          </tbody>
        </table>

        <p v-else class="vacio">No hay pedidos en curso.</p>
      </div>

      <div class="marco">
        <h2>Atajos</h2>
        <ul style="line-height: 2">
          <li><NuxtLink to="/pacientes">Pacientes</NuxtLink> — fichas y datos de contacto.</li>
          <li><NuxtLink to="/armazones">Armazones</NuxtLink> — catálogo con medidas de caja.</li>
          <li><NuxtLink to="/lentes">Lentes</NuxtLink> — material, índice y diámetro de corte.</li>
          <li v-if="puedeVerRecetas">
            <NuxtLink to="/recetas">Recetas</NuxtLink> — emisión, graduación y vencimiento.
          </li>
          <li v-if="puedeOperar">
            <NuxtLink to="/pedidos">Pedidos a laboratorio</NuxtLink> — encargo y fecha prometida.
          </li>
          <li v-if="puedeOperar">
            <NuxtLink to="/recepcion">Recepción y control de calidad</NuxtLink> — antes de entregar.
          </li>
        </ul>
      </div>
    </template>
  </div>
</template>