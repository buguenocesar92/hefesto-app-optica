<script setup lang="ts">
/**
 * Marco comun: encabezado, menu y contenido.
 *
 * EL MENU ES UNA CORTESIA, NO UNA PROTECCION
 * ------------------------------------------
 * Las secciones de recetas y graduaciones SOLO aparecen si la API dijo que la
 * persona puede verlas. Pero esconder un enlace no protege nada: si la API
 * devuelve 404 cuando la policy rechaza (ver docs/07), la seguridad esta ahi.
 *
 * Este menu existe para que el mostrador no vea un menu lleno de cosas que le
 * van a ser rechazadas.
 */
const { usuario, salir, puedeVerRecetas, puedeAdministrar, puedeOperar } = useSesion();

const ruta = useRoute();
</script>

<template>
  <div>
    <header
      v-if="usuario"
      style="
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        padding: 0.6rem 1rem;
        border-bottom: 1px solid var(--borde);
        background: var(--superficie);
      "
    >
      <strong>OpticaHefesto</strong>

      <span class="tenue">{{ usuario.optica?.nombre }}</span>

      <nav style="display: flex; gap: 0.8rem; flex-wrap: wrap; flex: 1">
        <NuxtLink to="/">Inicio</NuxtLink>
        <NuxtLink to="/pacientes">Pacientes</NuxtLink>
        <NuxtLink to="/armazones">Armazones</NuxtLink>
        <NuxtLink to="/lentes">Lentes</NuxtLink>

        <NuxtLink v-if="puedeOperar" to="/pedidos">Pedidos</NuxtLink>
        <NuxtLink v-if="puedeOperar" to="/recepcion">Recepcion</NuxtLink>
        <NuxtLink v-if="puedeOperar" to="/entregas">Entregas</NuxtLink>
        <NuxtLink v-if="puedeOperar" to="/ajustes">Ajustes</NuxtLink>

        <!--
            Las recetas son informacion de salud. El enlace no se muestra a
            quien no puede verlas, y aunque se lo saltara, la API devuelve 404.
        -->
        <NuxtLink v-if="puedeVerRecetas" to="/recetas">Recetas</NuxtLink>

        <NuxtLink v-if="puedeAdministrar" to="/administracion/cuentas">Cuentas</NuxtLink>
      </nav>

      <span class="tenue">
        {{ usuario.nombre }} ({{ usuario.rol_etiqueta }})
      </span>

      <button type="button" @click="salir">Salir</button>
    </header>

    <main style="padding: 1rem; max-width: 1100px; margin: 0 auto">
      <slot />
    </main>

    <footer
      v-if="usuario"
      class="tenue"
      style="padding: 1rem; text-align: center; border-top: 1px solid var(--borde)"
    >
      Software de Hefesto Studio. Los plazos legales y las tolerancias que
      todavia no se han verificado aparecen marcados como pendientes.
    </footer>
  </div>
</template>