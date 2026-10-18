<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administracion;

use App\Enums\RolOptica;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarUsuarioRequest;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;

/**
 * Cuentas de la optica.
 *
 * EL ROL LO DECIDE AUTHENTIK, NO ESTE PANTALLO
 * ------------------------------------------
 * El campo `rol` se acepta para dejar la cuenta costeada, pero se sobreescribe
 * en el primer ingreso segun los equipos a los que pertenezca la persona (ver
 * App\Services\Auth\ResolutorDeSesion). Esta pantalla no es la fuente de
 * verdad del permiso.
 *
 * LO QUE SI HACE ESTA PANTALLA
 * ---------------------------
 * Desactivar cuentas. Eso si es local y es urgente: si alguien deja de
 * trabajar en la optica, esperar a que alguien le quite el equipo en Authentik
 * deja una ventana de acceso abierta. Ver docs/07.
 */
class UsuarioController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Usuario::class);

        $usuarios = Usuario::query()
            ->orderBy('nombre')
            ->get()
            ->map(static fn (Usuario $usuario): array => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'correo' => $usuario->correo,
                'rol' => $usuario->rol->value,
                'rol_etiqueta' => $usuario->rol->etiqueta(),
                'activo' => $usuario->activo,
                'ultimo_ingreso_en' => $usuario->ultimo_ingreso_en?->format('Y-m-d H:i'),

                /*
                | Si la persona tiene permisos para leer graduaciones. Se
                | calcula en el servidor y no en el navegador, porque es una
                | pregunta de seguridad.
                */
                'lee_graduaciones' => in_array(
                    $usuario->rol,
                    RolOptica::conAccesoAGraduaciones(),
                    true
                ),
            ])
            ->all();

        return response()->json([
            'datos' => $usuarios,
            'roles' => array_map(
                static fn (RolOptica $rol): array => [
                    'valor' => $rol->value,
                    'etiqueta' => $rol->etiqueta(),
                    'equipo_authentik' => $rol->equipoAuthentik(),
                    'lee_graduaciones' => in_array(
                        $rol,
                        RolOptica::conAccesoAGraduaciones(),
                        true
                    ),
                ],
                RolOptica::cases(),
            ),
        ]);
    }

    public function store(GuardarUsuarioRequest $peticion): JsonResponse
    {
        $this->authorize('create', Usuario::class);

        $existente = Usuario::query()
            ->where('tenant_id', $peticion->user()->tenant_id)
            ->where('correo', mb_strtolower($peticion->string('correo')))
            ->first();

        if ($existente !== null) {
            return response()->json([
                'mensaje' => 'Ya existe una cuenta con ese correo en esta optica.',
            ], 409);
        }

        $usuario = Usuario::query()->create([
            'nombre' => $peticion->string('nombre'),
            'correo' => mb_strtolower($peticion->string('correo')),
            'rol' => $peticion->input('rol', RolOptica::Mostrador->value),
            'activo' => $peticion->boolean('activo', true),
        ]);

        return response()->json([
            'id' => $usuario->id,
            'nombre' => $usuario->nombre,
            'correo' => $usuario->correo,
            'mensaje' => 'Cuenta creada. El rol efectivo lo decide Authentik segun los equipos '
                .'a los que pertenezca la persona.',
        ], 201);
    }

    /**
     * Desactivar una cuenta.
     *
     * Nadie puede desactivarse a si mismo: sin ese limite, un administrador
     * puede dejar la optica sin ninguna cuenta valida y nadie mas entra a
     * arreglarlo.
     */
    public function desactivar(Usuario $usuario): JsonResponse
    {
        $this->authorize('desactivarSelf', $usuario);

        $usuario->activo = false;
        $usuario->save();

        return response()->json([
            'id' => $usuario->id,
            'activo' => false,
            'mensaje' => 'Cuenta desactivada. La persona deja de entrar en el siguiente intento.',
        ]);
    }
}