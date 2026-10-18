<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\RolOptica;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reserva una ruta al rol de administracion de la optica.
 *
 * Para el catalogo, las cuentas y la bitacora.
 *
 * Un rechazo responde 404 y no 403, igual que en el resto de la aplicacion:
 * ver docs/07.
 */
class SoloAdministrador
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user();

        if ($usuario === null || $usuario->rol !== RolOptica::Administrador->value) {
            abort(404);
        }

        return $siguiente($peticion);
    }
}