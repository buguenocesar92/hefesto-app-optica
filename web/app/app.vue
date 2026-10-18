<script setup lang="ts">
/**
 * Carga la sesion antes de que se monte la aplicacion.
 *
 * Se hace aca y no en cada pagina porque el layout y el middleware necesitan
 * saber quien esta operando, y sin esto la primera pantalla llegaria a
 * dibujarse con el menu vacio.
 */
const { cargar } = useSesion();

await cargar();
</script>

<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>