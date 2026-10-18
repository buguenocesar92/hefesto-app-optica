<script setup lang="ts">
import type { Rol } from '~/tipos/api';

/**
 * Cuentas de la óptica.
 *
 * EL ROL LO DECIDE AUTHENTIK, NO ESTA PANTALLA
 * -------------------------------------------
 * El campo «rol» que se ve acá es el último que se resolvió al ingresar. La
 * fuente de verdad es a qué equipos pertenece la persona en Authentik, y se
 * vuelve a leer en cada ingreso (ver App\Services\Auth\ResolutorDeSesion).
 *
 * LO QUE ESTA PANTALLA SÍ HACE
 * ---------------------------
 * Desactivar cuentas. Eso es local y es urgente: si alguien deja de trabajar
 * en la óptica, esperar a que alguien le quite el equipo en Authentik deja una
 * ventana de acceso abierta.
 *
 * Ver docs/04 y docs/07.
 */
definePageMeta({ middleware: 'auth' });

interface Fila {
  id: string;
  nombre: string;
  correo: string;
  rol: Rol;
  rol_etiqueta: string;
  activo: boolean;
  ultimo_ingreso_en: string | null;
  lee_graduaciones: boolean;
}

const cuentas = ref<Fila[]>([]);
const roles = ref<{ valor: Rol; etiqueta: string; equipo_authentik: string; lee_graduaciones: boolean }[]>([]);
const cargando = ref(true);
const error = ref<string | null>(null);

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    const respuesta = await $fetch<{
      datos: Fila[];
      roles: { valor: Rol; etiqueta: string; equipo_authentik: string; lee_graduaciones: boolean }[];
    }>('/api/v1/admin/usuarios');

    cuentas.value = respuesta.datos;
    roles.value = respuesta.roles;
  } catch {
    error.value = 'No se pudo cargar el listado de cuentas.';
  } finally {
    cargando.value = false;
  }
}

async function desactivar(cuenta: Fila): Promise<void> {
  error.value = null;

  try {
    await $fetch(`/api/v1/admin/usuarios/${cuenta.id}/desactivar`, { method: 'POST' });

    await cargar();
  } catch (e: any) {
    error.value = e?.data?.message ?? 'No se pudo desactivar la cuenta.';
  }
}

onMounted(cargar);
</script>

<template>
  <div>
    <h1>Cuentas</h1>

    <p class="tenue">
      No hay contraseñas en esta aplicación. La identidad se resuelve en Authentik
      y el rol se vuelve a leer en cada ingreso.
    </p>

    <div v-if="error" class="aviso-error">{{ error }}</div>
    <div v-if="cargando" class="vacio">Cargando…</div>

    <table v-else-if="cuentas.length">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Correo</th>
          <th>Rol</th>
          <th>Lee graduaciones</th>
          <th>Último ingreso</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="cuenta in cuentas" :key="cuenta.id">
          <td>{{ cuenta.nombre }}</td>
          <td>{{ cuenta.correo }}</td>
          <td>{{ cuenta.rol_etiqueta }}</td>
          <td>
            <span v-if="cuenta.lee_graduaciones" class="estado peligro">sí</span>
            <span v-else class="estado neutro">no</span>
          </td>
          <td class="tenue">{{ cuenta.ultimo_ingreso_en ?? 'nunca' }}</td>
          <td>
            <span :class="cuenta.activo ? 'estado ok' : 'estado neutro'">
              {{ cuenta.activo ? 'Activa' : 'Desactivada' }}
            </span>
          </td>
          <td>
            <button
              v-if="cuenta.activo"
              class="peligro"
              type="button"
              @click="desactivar(cuenta)"
            >
              Desactivar
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <p v-else class="vacio">No hay cuentas.</p>

    <div class="marco" style="margin-top: 1.5rem">
      <h2>Equipos en Authentik</h2>
      <p class="tenue">
        Para que alguien entre, tiene que pertenecer a un equipo de rol y al
        equipo que declara la óptica.
      </p>

      <table>
        <thead>
          <tr>
            <th>Rol</th>
            <th>Equipo en Authentik</th>
            <th>Lee graduaciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rol in roles" :key="rol.valor">
            <td>{{ rol.etiqueta }}</td>
            <td><code>{{ rol.equipo_authentik }}</code></td>
            <td>{{ rol.lee_graduaciones ? 'sí' : 'no' }}</td>
          </tr>
        </tbody>
      </table>

      <p class="tenue" style="margin-top: 0.6rem">
        Y además al equipo <code>OpticaHefesto:&lt;correlacion&gt;</code>, donde la
        correlación es la de la óptica.
      </p>
    </div>
  </div>
</template>