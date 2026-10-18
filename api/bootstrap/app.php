<?php

declare(strict_types=1);

use App\Http\Middleware\AuditarReceta;
use App\Http\Middleware\ResolverTenant;
use App\Http\Middleware\SoloAdministrador;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/**
 * Punto de arranque de la API de OpticaHefesto.
 *
 * Nota sobre el esqueleto: este archivo reemplaza al que genera
 * `composer create-project laravel/laravel`. El resto del esqueleto
 * (artisan, public/index.php, storage) lo genera Composer. Ver docs/04.
 *
 * Todas las rutas cuelgan del grupo `web` de Laravel porque la interfaz (Nuxt)
 * se autentica con cookie de sesion: la cookie viaja en el mismo origen y el
 * token CSRF se reenvia como cabecera X-XSRF-TOKEN. Ver docs/01.
 *
 * Un rechazo de autorizacion responde 404, no 403. En esta aplicacion la razon
 * es mas fuerte que en un sistema de inventario: la graduacion de una receta es
 * informacion de salud, asi que un 403 le confirmaria a quien pregunta que el
 * paciente existe en otra optica. Ver docs/07.
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ResolverTenant envuelve la peticion autenticada en una transaccion
        // con la empresa ya fijada, para que la RLS sepa de quien son las
        // filas. Corre ANTES que `auth`, por eso resuelve el usuario por su
        // cuenta.
        $middleware->web(append: [
            ResolverTenant::class,
        ]);

        $middleware->alias([
            // Marca las rutas que abren recetas y graduaciones. El middleware
            // anota cada intento en `accesos_recetas`, permitido o denegado.
            'receta.auditada' => AuditarReceta::class,

            // Administracion de la optica: catalogo, usuarios y bitacora.
            // El mostrador y el optometrista no entran.
            'admin' => SoloAdministrador::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'mensaje' => 'No encontrado.',
                ], 404);
            }

            return null;
        });
    })
    ->create();