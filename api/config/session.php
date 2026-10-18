<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Controlador de sesion
    |--------------------------------------------------------------------------
    |
    | La sesion vive en la base de datos, no en un archivo. Es la unica opcion
    | que sirve cuando hay varios contenedores de API: una sesion en archivo
    | quedaria en el contenedor que la creo y el siguiente ingreso no la
    | encontraria.
    |
    */

    'driver' => env('SESSION_DRIVER', 'database'),

    'lifetime' => (int) env('SESSION_EXPIRACION_MINUTOS', 120),
    'expire_on_close' => false,

    /*
    | En produccion tiene que ser `true`: sin HTTPS la cookie de sesion viaja
    | en claro, y con ella el acceso a las graduaciones. Ver docs/07.
    */
    'secure' => env('SESSION_SECURE_COOKIE', false),

    'http_only' => true,

    /*
    | `lax` y no `strict`: con `strict` la cookie no viaja cuando se llega
    | desde un enlace externo, que es justo como se entra desde el correo de
    | confirmacion de una entrega.
    */
    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    'partitioned' => false,

];