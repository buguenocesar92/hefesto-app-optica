import type { Usuario } from '~/tipos/api';

/**
 * Sesion de la persona que esta operando la optica.
 *
 * EL ROL DECIDE QUE SE VE EN EL MENU, NO QUE SE PUEDE HACER
 * -------------------------------------------------------
 * Las capacidades vienen de la API. Esta composable las usa para esconder
 * botones, que es una cortesia: la seguridad esta en la API, que devuelve 404
 * cuando una policy rechaza y no entrega el dato.
 *
 * Mostrar un boton que va a ser rechazado es molesto. Poder hacer lo que el
 * boton hacia, no depende de esta archivo. Ver docs/09.
 */
export function useSesion() {
  const usuario = useState<Usuario | null>('sesion:usuario', () => null);
  const cargado = useState<boolean>('sesion:cargada', () => false);

  async function cargar(): Promise<Usuario | null> {
    if (cargado.value) {
      return usuario.value;
    }

    try {
      usuario.value = await $fetch<Usuario>('/api/v1/yo');
    } catch {
      usuario.value = null;
    }

    cargado.value = true;

    return usuario.value;
  }

  async function salir(): Promise<void> {
    await $fetch('/api/v1/acceso/salir', { method: 'POST' });

    usuario.value = null;
    cargado.value = true;

    await navigateTo('/entrar');
  }

  /*
  | Los tres filtros que hacen falta para esconder botones. La regla que mas
  | importa es `puedeVerRecetas`: la graduacion es informacion de salud y solo
  | la ve el optometrista. Ver docs/07.
  */
  const puedeVerRecetas = computed(() => usuario.value?.capacidades?.puede_ver_recetas === true);
  const puedeAdministrar = computed(() => usuario.value?.capacidades?.puede_administrar_catalogo === true);
  const puedeOperar = computed(() => usuario.value?.capacidades?.puede_gestionar_pedidos === true);

  return {
    usuario,
    cargado,
    cargar,
    salir,
    puedeVerRecetas,
    puedeAdministrar,
    puedeOperar,
  };
}