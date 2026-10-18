/**
 * Protege las pantallas de la optica.
 *
 * Si no hay sesion, manda a la pantalla de entrada.
 *
 * NO ES UNA PROTECCION DE SEGURIDAD
 * ---------------------------------
 * La seguridad esta en la API, que devuelve 404 cuando una policy rechaza y no
 * entrega el dato. Esto es solo para que la persona no vea una pantalla vacia
 * sin saber que hacer.
 *
 * Un boton que se puede apretar y leer graduaciones es un problema de
 * orientacion para quien lo usa, no un problema de seguridad. Ver docs/09.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  const { usuario, cargar } = useSesion();

  const quien = await cargar();

  if (!quien && to.path !== '/entrar') {
    return navigateTo('/entrar');
  }

  if (quien && to.path === '/entrar') {
    return navigateTo('/');
  }

  return usuario.value !== null || to.path === '/entrar';
});