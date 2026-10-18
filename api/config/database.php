<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Conexiones de base de datos
|------------------------------------------------------------------------------
|
| Hay DOS conexiones de PostgreSQL a proposito:
|
|  - `pgsql`: la de la peticion. Corre dentro de una transaccion con el
|    contexto de optica fijado, que es lo que leen las politicas RLS.
|
|  - `auditoria`: la de la bitacora de accesos a recetas. Es una conexion
|    APARTE porque la peticion puede terminar revertida (acceso denegado) y el
|    registro del intento tiene que sobrevivir a esa reversa.
|    Ver App\Services\Recetas\BitacoraRecetas.
|
| Es la misma base de datos por defecto. Se pueden separar fisicamente con las
| variables `DB_AUDITORIA_*` si la optica lo necesita: es una de las
| separaciones que suele pedir una auditoria externa. Ver docs/07.
|
*/

return [

    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'optica'),
            'username' => env('DB_USERNAME', 'optica'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            // La aplicacion no usa SSL contra PostgreSQL porque los contenedores
            // se comunican por la red interna de Compose. Si la base queda
            // afuera, hay que poner `DB_SSLMODE=require` y revisar la cadena
            // de conexion. Ver docs/04.
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'auditoria' => [
            'driver' => 'pgsql',
            'host' => env('DB_AUDITORIA_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('DB_AUDITORIA_PORT', env('DB_PORT', '5432')),
            'database' => env('DB_AUDITORIA_DATABASE', env('DB_DATABASE', 'optica')),
            'username' => env('DB_AUDITORIA_USERNAME', env('DB_USERNAME', 'optica')),
            'password' => env('DB_AUDITORIA_PASSWORD', env('DB_PASSWORD', '')),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_AUDITORIA_SSLMODE', env('DB_SSLMODE', 'prefer')),
        ],

    ],

    /*
    | Lecturas MySQL quedan para que la estructura sea la misma que en las
    | demas aplicaciones de Hefesto. OpticaHefesto NO usa MySQL: la RLS es de
    | PostgreSQL y no se negocia. Ver docs/01.
    */
    'mysql' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'optica'),
        'username' => env('DB_USERNAME', 'optica'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
    ],

    'sqlite' => [
        'driver' => 'sqlite',
        'database' => env('DB_DATABASE', database_path('database.sqlite')),
        'prefix' => '',
        'foreign_key_constraints' => true,
    ],

    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),

        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

];