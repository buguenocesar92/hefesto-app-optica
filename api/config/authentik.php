<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Authentik (OpenID Connect)
    |--------------------------------------------------------------------------
    |
    | OpticaHefesto usa la misma identidad que las demas aplicaciones de
    | Hefesto: el kit `kits/hefesto-identidad-authentik`. Cada aplicacion tiene
    | su PROPIO cliente OIDC en Authentik, apuntando a su propia URL de
    | retorno, para poder revocar el acceso a una aplicacion sin tocar las
    | demas. Ver docs/05.
    |
    | `issuer` es la URL publica del proveedor, sin barra final. A partir de
    | ella se descubre el resto: `.well-known/openid-configuration`. Eso es
    | descubrimiento de OpenID Connect, no una ruta inventada.
    |
    */

    'issuer' => env('AUTHENTIK_ISSUER'),

    'client_id' => env('AUTHENTIK_CLIENT_ID'),

    'client_secret' => env('AUTHENTIK_CLIENT_SECRET'),

    'redirect_uri' => env('AUTHENTIK_REDIRECT_URI'),

    /*
    | Estado (anti CSRF del flujo OIDC). `session` guarda el valor en la
    | cookie firmada, no en la base.
    */
    'state' => 'session',

    /*
    | Alcances pedidos. `openid` y `profile` son obligatorios en OIDC.
    | `email` permite ver el correo de la persona.
    */
    'scopes' => array_values(array_filter([
        env('AUTHENTIK_SCOPES', 'openid profile email'),
    ])),

    /*
    | Tiempo de vida de la metadata descubierta y del JWKS, en segundos.
    | El JWKS se cachea mas rato porque las claves rotan pocas veces.
    */
    'cache_metadata' => (int) env('AUTHENTIK_CACHE_METADATA', 3600),
    'cache_jwks' => (int) env('AUTHENTIK_CACHE_JWKS', 86400),

    /*
    | Algoritmo de firma esperado en el token de identificacion. Se comprueba
    | ANTES de verificar la firma, para rechazar el ataque de confusion de
    | algoritmo (cambiar RS256 por HS256 y firmar con la clave publica).
    */
    'algoritmo' => env('AUTHENTIK_ALGORITMO', 'RS256'),

    /*
    | Margen de reloj al revisar `exp`, `iat` y `nbf`, en segundos.
    */
    'leeway' => (int) env('AUTHENTIK_LEEWAY', 60),

    /*
    |--------------------------------------------------------------------------
    | Equipos de Authentik a rol de OpticaHefesto
    |--------------------------------------------------------------------------
    |
    | El rol NO viaja en el token: se deduce de los equipos a los que la
    | persona pertenece en Authentik. La precedencia importa: si alguien esta
    | en varios equipos gana el primero de esta lista, asi que `administrador`
    | va primero.
    |
    | Los tres roles son:
    |
    |   mostrador      ve pacientes, catalogo, pedidos y entregas
    |   optometrista   ve ademas recetas y graduaciones
    |   administrador  administra la optica, y NO lee graduaciones
    |
    | El mapa arranca VACIO a proposito: mientras no se llene, no se puede
    | iniciar sesion. Es preferible a que entre cualquiera con el rol de
    | siempre. Ver docs/03 y docs/04.
    |
    */
    'grupos_por_rol' => [
        // 'Administradores de OpticaHefesto' => 'administrador',
        // 'Optometristas de OpticaHefesto' => 'optometrista',
        // 'Mostrador de OpticaHefesto' => 'mostrador',
    ],

    /*
    | Prefijo de los equipos de Authentik que declaran la optica (tenant).
    |
    | El equipo se llama `<prefijo>:<correlacion>`, por ejemplo
    | `OpticaHefesto:centro`, y `correlacion` se busca en la columna
    | `opticas.correlacion_identidad`. Ver docs/04.
    |
    | Sin un equipo que declare una correlacion no hay ingreso.
    */
    'grupo_por_optica' => env('AUTHENTIK_GRUPO_POR_OPTICA', 'OpticaHefesto'),

];