/**
 * Proxy de datos de OpticaHefesto.
 *
 * QUE HACE
 * --------
 * El navegador habla siempre con `/api/...` en su propio origen. Este
 * archivo saca el prefijo `/api` y reenvia el resto a la API de Laravel.
 *
 *     /api/v1/yo   ->   {apiBase}/v1/yo
 *     /api/v1/pacientes -> {apiBase}/v1/pacientes
 *
 * QUE HACE DE MAS EN `api/v1/acceso/entrar`
 * ------------------------------------------
 * Las tres rutas de ingreso devuelven una URL a la que hay que ir, y no JSON.
 * Por eso se atienden acá y no en la API: quien hace la redireccion es el
 * navegador, y la respuesta tiene que ser del mismo origen para no perder la
 * cookie de sesion.
 *
 * POR QUE NO VA DIRECTO DESDE EL NAVEGADOR
 * ---------------------------------------
 * En desarrollo, porque el navegador esta en `localhost:3000` y la API en
 * `localhost:8000`: son dos origenes y el navegador lo bloquea.
 *
 * En produccion, por una razon mas importante: la sesion viaja en una cookie
 * de la API. Si el navegador hablara directo con la API, la cookie tendria que
 * ir con `SameSite=None`, y quedaria expuesta a cualquier sitio. Con el proxy,
 * el navegador nunca ve el origen de la API y la cookie puede seguir siendo
 * `HttpOnly` y `SameSite=Lax`. Ver docs/01 y docs/07.
 */

export default defineEventHandler(async (event) => {
  const url = getRequestURL(event);
  const metodo = getMethod(event);

  // `/api/v1/articulos` -> `v1/articulos`
  const ruta = url.pathname.replace(/^\/api\/?/, '');
  const parametros = url.search;

  // Estas tres son de ingreso: devuelven una URL, no datos.
  if (ruta === 'v1/acceso/entrar') {
    return await proxiar(event, metodo, ruta, parametros, true);
  }

  return proxiar(event, metodo, ruta, parametros, false);
});

/**
 * Reenvia una peticion a la API y devuelve lo que ella respondio.
 *
 * @param  boolean  esIngreso  Cuando es `true`, no se manda al login si falla:
 *                            el usuario todavia no tiene sesion y no hay nada
 *                            a lo que volver.
 */
async function proxiar(
  event: any,
  metodo: string,
  ruta: string,
  parametros: string,
  esIngreso: boolean,
): Promise<unknown> {
  const config = useRuntimeConfig(event);

  const destino = new URL(`${String(config.apiBase).replace(/\/+$/, '')}/${ruta}`);
  destino.search = parametros;

  const cabeceras: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  };

  // La cookie de sesion y el token CSRF viajan tal cual. El servidor de Nuxt
  // NO lee la cookie ni la transforma: solo la copia.
  const cookie = getRequestHeader(event, 'cookie');
  if (cookie) {
    cabeceras.cookie = cookie;
  }

  const csrf = getRequestHeader(event, 'x-xsrf-token');
  if (csrf) {
    cabeceras['x-xsrf-token'] = csrf;
  }

  const agente = getRequestHeader(event, 'user-agent');
  if (agente) {
    cabeceras['user-agent'] = agente;
  }

  let respuesta: Response;

  try {
    respuesta = await fetch(destino.toString(), {
      method: metodo,
      headers: cabeceras,
      // El cuerpo se reenvia tal cual: la API sigue siendo la que valida.
      body: metodo === 'GET' || metodo === 'HEAD' ? undefined : await cuerpoDe(event),
    });
  } catch {
    throw createError({
      statusCode: 502,
      statusMessage: 'No se pudo contactar la API',
      data: { mensaje: 'No se pudo contactar el servidor de la aplicacion.' },
    });
  }

  /*
   * SESION CAIDA
   * ------------
   * Un 401 significa que la cookie vencio o que la cuenta se desactivo. Se
   * manda a la pantalla de entrada con un aviso, en vez de dejar una pantalla
   * a medio cargar.
   */
  if (respuesta.status === 401 && !esIngreso) {
    return sendRedirect(event, '/entrar?error=sesion', 302);
  }

  /*
   * EL CODIGO DE ESTADO SE REENVIA TAL COMO
   * ---------------------------------------
   * En especial el 404. Cuando una policy rechaza, la API responde 404 a
   * proposito, para no confirmar que el recurso existe (ver docs/07). Si el
   * proxy lo cambiara por un 500, la interfaz mostraria un error de sistema
   * donde en realidad es "no lo puedes ver".
   */
  const cuerpo = await respuesta.text();

  for (const [nombre, valor] of respuesta.headers) {
    if (nombre.toLowerCase() === 'set-cookie') {
      appendResponseHeader(event, 'set-cookie', valor);
    }
  }

  setResponseStatus(event, respuesta.status);

  try {
    return cuerpo === '' ? null : JSON.parse(cuerpo);
  } catch {
    return cuerpo;
  }
}

/**
 * El cuerpo de la peticion, como cadena.
 *
 * En un GET no hay nada que leer, y `readBody` en un GET deja la peticion en un
 * estado inutilizable. Por eso solo se lee cuando el metodo lo trae.
 */
async function cuerpoDe(event: any): Promise<string | undefined> {
  const metodo = getMethod(event);

  if (metodo === 'GET' || metodo === 'HEAD' || metodo === 'OPTIONS') {
    return undefined;
  }

  const cuerpo = await readBody(event);

  if (cuerpo === undefined || cuerpo === null) {
    return undefined;
  }

  if (typeof cuerpo === 'string') {
    return cuerpo;
  }

  return JSON.stringify(cuerpo);
}