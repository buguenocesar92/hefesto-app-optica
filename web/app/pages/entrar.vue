<script setup lang="ts">
import type { OpticaPublica } from '~/tipos/api';

/**
 * Pantalla de ingreso.
 *
 * EL SELECTOR DE EMPRESA USA LA VISTA PÚBLICA
 * -------------------------------------------
 * `GET /api/v1/acceso/opticas` responde con lo que devuelve
 * `App\Models\Optica::publicas()`, que lee `vw_opticas_publicas`: tres columnas
 * y solo opticas activas.
 *
 * No se pide la tabla cruda por ningun motivo. `correlacion_identidad` es la
 * llave que liga a una persona con una optica en Authentik, y la vista no la
 * expone. Ver docs/03.
 *
 * LO QUE PASA DESPUES
 * -------------------
 * El ingreso real lo hace Authentik. Esta pantalla no pide usuario ni
 * contrasena: pide que se elija la empresa y manda al proveedor de identidad.
 * Ver docs/04.
 */
const { usuario } = useSesion();

const opticas = ref<OpticaPublica[]>([]);
const elegida = ref<string>('');
const cargando = ref(true);
const error = ref<string | null>(null);

onMounted(async () => {
  try {
    const respuesta = await $fetch<{ opticas: OpticaPublica[] }>('/api/v1/acceso/opticas');

    opticas.value = respuesta.opticas;
    elegida.value = respuesta.opticas[0]?.slug ?? '';
  } catch {
    error.value = 'No se pudo cargar la lista de opticas.';
  } finally {
    cargando.value = false;
  }
});

async function entrar(): Promise<void> {
  if (!elegida.value) {
    error.value = 'Elija una optica.';
    return;
  }

  error.value = null;

  try {
    /*
     * La empresa elegida viaja en la sesion de Nuxt como estado del flujo, y la
     * API la contrasta contra el equipo de Authentik al volver del proveedor.
     * Elegirla aca NO da acceso a nada: es solo para saber a quien preguntar.
     */
    sessionStorage.setItem('optica:slug', elegida.value);

    const respuesta = await $fetch<{ url: string }>('/api/v1/acceso/entrar', {
      method: 'POST',
    });

    window.location.href = respuesta.url;
  } catch {
    error.value =
      'No se pudo iniciar el ingreso. Revise la configuracion de Authentik (docs/05).';
  }
}
</script>

<template>
  <div style="max-width: 26rem; margin: 3rem auto">
    <div class="marco">
      <h1>OpticaHefesto</h1>
      <p class="tenue">
        La identidad se resuelve en el proveedor único del estudio. Acá se elige
        en qué óptica se entra.
      </p>

      <div v-if="cargando" class="vacio">Cargando…</div>

      <div v-else-if="error" class="aviso-error">{{ error }}</div>

      <template v-else>
        <div class="campo">
          <label for="optica">Óptica</label>
          <select id="optica" v-model="elegida" :disabled="usuario !== null">
            <option v-for="o in opticas" :key="o.id" :value="o.slug">
              {{ o.nombre }}
            </option>
          </select>

          <p v-if="opticas.length === 0" class="tenue" style="margin-top: 0.4rem">
            No hay ópticas activas en esta instalación.
          </p>
        </div>

        <button class="primario" type="button" :disabled="opticas.length === 0" @click="entrar">
          Ingresar
        </button>
      </template>
    </div>

    <p class="tenue" style="text-align: center">
      No hay usuarios ni contraseñas en esta aplicación.
      Ver <code>docs/04-INSTALACION.md</code>.
    </p>
  </div>
</template>