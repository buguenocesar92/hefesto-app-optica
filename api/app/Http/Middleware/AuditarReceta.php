<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\RolOptica;
use App\Services\Recetas\BitacoraRecetas;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Anota en la bitacora el intento de abrir una receta o una graduacion.
 *
 * SE REGISTRA TANTO LO QUE SE PUDO ABRIR COMO LO QUE NO
 * ----------------------------------------------------
 * Un intento denegado dice tanto como uno permitido. Es la unica forma de
 * enterarse de que alguien esta probando el sistema con el rol equivocado, y
 * en una optica ese alguien podria ser una cuenta compartida que se paso de
 * ambitio.
 *
 * QUE NO SE REGISTRA
 * ------------------
 * Ningun valor de graduacion. Solo el id del recurso, quien lo pidio, cuando y
 * si se le permitio. La bitacora no puede convertirse en una segunda copia de
 * la informacion de salud. Ver docs/07.
 *
 * SI LA BITACORA FALLA, LA PETICION CONTINUA
 * ------------------------------------------
 * Un registro de bitacora que tumba el sistema es peor que uno que falta.
 */
class AuditarReceta
{
    public function __construct(private readonly BitacoraRecetas $bitacora) {}

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $recurso = $peticion->route()?->getName() !== null && str_contains((string) $peticion->route()?->getName(), 'graduaciones')
            ? 'graduacion'
            : 'receta';

        $recursoId = $peticion->route('receta') ?? $peticion->route('graduacion');

        $usuario = $peticion->user();

        if ($usuario !== null && ! $this->puedeVerGraduaciones($usuario->rol)) {
            $this->bitacora->registrar(
                $recurso,
                is_string($recursoId) ? $recursoId : null,
                'ver',
                false,
                'El rol '.$usuario->rol.' no tiene acceso a graduaciones.',
                $peticion,
                $usuario->id,
            );

            // 404 y no 403: ver docs/07. Un 403 confirmaria que el recurso
            // existe.
            abort(404);
        }

        try {
            $respuesta = $siguiente($peticion);
        } catch (Throwable $e) {
            $this->bitacora->registrar(
                $recurso,
                is_string($recursoId) ? $recursoId : null,
                'ver',
                false,
                'La peticion fallo antes de resolver el recurso.',
                $peticion,
                $usuario?->id,
            );

            throw $e;
        }

        $this->bitacora->registrar(
            $recurso,
            is_string($recursoId) ? $recursoId : null,
            'ver',
            true,
            null,
            $peticion,
            $usuario?->id,
        );

        return $respuesta;
    }

    /**
     * Si el rol puede leer graduaciones.
     */
    private function puedeVerGraduaciones(mixed $rol): bool
    {
        $roles = array_map(
            static fn (RolOptica $caso): string => $caso->value,
            RolOptica::conAccesoAGraduaciones()
        );

        return in_array($rol, $roles, true);
    }
}