/**
 * Configuracion de Nuxt para OpticaHefesto.
 *
 * SSR APAGADO, A PROPOSITO
 * ------------------------
 * La aplicacion maneja datos de salud: recetas y graduaciones. Con SSR, cada
 * pagina se renderiza en el servidor de Nuxt y el HTML llega al navegador con
 * el contenido ya puesto, que queda en el historial, en la cache del
 * navegador y en los proxies intermedios.
 *
 * Ademas, el ingreso es con cookie de sesion contra la API de Laravel. Con
 * SSR, cada renderizado del servidor tendria que reenviar esa cookie hacia
 * adelante, y un fallo de reenvio se traduce en una pantalla vacia sin
 * explicacion.
 *
 * Con SPA el navegador pide los datos a traves del proxy de Nuxt, y el
 * servidor no toca datos de salud. Ver docs/01.
 *
 * EL PROXY DE DATOS
 * -----------------
 * `server/api/[...ruta].ts` reenvia al servidor de Nuxt, no el navegador a la
 * API. En desarrollo eso evita el problema de CORS; en produccion mantiene el
 * mismo origen, que es lo que permite la cookie de sesion.
 *
 * La cookie viaja sola porque el proxy es del mismo origen que la pagina. La
 * peticion hacia la API se hace en el servidor, con el reenvio explicito de
 * la cookie y del token CSRF.
 */
export default defineNuxtConfig({
  compatibilityDate: '2025-01-01',

  devtools: { enabled: false },

  ssr: false,

  css: ['~/assets/css/main.css'],

  runtimeConfig: {
    // Config PRIVADA: solo la usa el servidor de Nuxt para reenviar las
    // llamadas a la API de Laravel. El navegador nunca la ve, y por eso
    // nunca se expone como `NUXT_PUBLIC_*`.
    apiBase: process.env.NUXT_API_BASE || 'http://localhost:8000',

    public: {
      base: process.env.NUXT_PUBLIC_BASE || 'http://localhost:3000',
    },
  },

  app: {
    head: {
      title: 'OpticaHefesto',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        {
          name: 'description',
          content: 'Optica y contactologia para pequenas y medianas opticas.',
        },
        // La graduacion es informacion de salud. Que los buscadores no la
        // indexen es una medida de sobra, no una garantia.
        { name: 'robots', content: 'noindex, nofollow' },
      ],
      htmlAttrs: { lang: 'es-CL' },
    },
  },
})