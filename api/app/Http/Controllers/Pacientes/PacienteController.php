<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pacientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarPacienteRequest;
use App\Models\Paciente;
use App\Services\Recetas\Vencimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fichas de pacientes.
 *
 * LO QUE SE VE DESDE ACA
 * ----------------------
 * La ficha es dato de contacto y facturacion. NO incluye graduaciones: para
 * eso esta `recetas`, que tiene su propia policy, mas restrictiva. Un paciente
 * se muestra con su ultima receta y su estado de vencimiento SI quien mira
 * tiene permiso para ver graduaciones.
 */
class PacienteController extends Controller
{
    public function index(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', Paciente::class);

        $busqueda = (string) $peticion->query('q', '');
        $porPagina = min(max((int) $peticion->query('por_pagina', 50), 1), 200);

        $pacientes = Paciente::query()
            ->buscar($busqueda)
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->paginate($porPagina);

        return response()->json([
            'datos' => collect($pacientes->items())
                ->map(fn (Paciente $paciente): array => $this->resumen($paciente))
                ->all(),
            'paginacion' => [
                'pagina' => $pacientes->currentPage(),
                'por_pagina' => $pacientes->perPage(),
                'total' => $pacientes->total(),
            ],
        ]);
    }

    public function store(GuardarPacienteRequest $peticion): JsonResponse
    {
        $this->authorize('create', Paciente::class);

        $paciente = Paciente::query()->create($peticion->validated());

        return response()->json($this->completo($paciente), 201);
    }

    public function show(Request $peticion, Paciente $paciente): JsonResponse
    {
        $this->authorize('view', $paciente);

        return response()->json($this->completo($paciente));
    }

    public function update(GuardarPacienteRequest $peticion, Paciente $paciente): JsonResponse
    {
        $this->authorize('update', $paciente);

        $paciente->fill($peticion->validated())->save();

        return response()->json($this->completo($paciente->fresh()));
    }

    /**
     * Desactivar, nunca borrar.
     *
     * Un paciente con recetas y pedidos es informacion de salud y un documento
     * comercial. Desactivarlo lo saca de los listados sin destruir nada.
     */
    public function desactivar(GuardarPacienteRequest $peticion, Paciente $paciente): JsonResponse
    {
        $this->authorize('desactivar', $paciente);

        $paciente->activo = $peticion->boolean('activo');
        $paciente->save();

        return response()->json($this->completo($paciente->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function resumen(Paciente $paciente): array
    {
        return [
            'id' => $paciente->id,
            'rut' => $paciente->rut,
            'nombre' => $paciente->nombreCompleto(),
            'telefono' => $paciente->telefono,
            'activo' => $paciente->activo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function completo(Paciente $paciente): array
    {
        $datos = $this->resumen($paciente);

        $datos['nombres'] = $paciente->nombres;
        $datos['apellidos'] = $paciente->apellidos;
        $datos['correo'] = $paciente->correo;
        $datos['fecha_nacimiento'] = $paciente->fecha_nacimiento?->format('Y-m-d');
        $datos['edad_anios'] = $paciente->fecha_nacimiento === null
            ? null
            : Vencimiento::edadEnAnios($paciente->fecha_nacimiento, now());

        /*
        | Si quien mira puede leer graduaciones, se agrega la ultima receta con
        | su estado de vencimiento. Si no puede, la respuesta NO dice que
        | existen recetas: mostrador y administracion no tienen por que saber
        | que ese paciente tiene historial optico.
        */
        if ($this->puedeVerGraduaciones($paciente)) {
            $ultima = $paciente->recetas()
                ->with('optometrista')
                ->orderByDesc('fecha_emision')
                ->first();

            if ($ultima !== null) {
                $datos['ultima_receta'] = [
                    'id' => $ultima->id,
                    'numero' => $ultima->numero,
                    'fecha_emision' => $ultima->fecha_emision->format('Y-m-d'),
                    'estado' => $ultima->estado->value,
                    'vencimiento' => $ultima->vencimiento()->aArray(),
                ];
            }
        }

        return $datos;
    }

    /**
     * Si la persona que esta mirando puede leer graduaciones.
     */
    private function puedeVerGraduaciones(Paciente $paciente): bool
    {
        return auth()->user()?->can('verGraduaciones', $paciente) ?? false;
    }
}