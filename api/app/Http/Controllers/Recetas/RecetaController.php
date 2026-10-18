<?php

declare(strict_types=1);

namespace App\Http\Controllers\Recetas;

use App\Enums\EstadoReceta;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmitirRecetaRequest;
use App\Http\Requests\GuardarGraduacionesRequest;
use App\Models\Graduacion;
use App\Models\Receta;
use App\Services\Recetas\CalculadoraDeVencimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Recetas y sus graduaciones.
 *
 * LA RUTA DE ESTE CONTROLADOR NO LA ABRE MOSTRADOR NI ADMINISTRACION
 * ------------------------------------------------------------------
 * Toda ruta de receta lleva el middleware `receta.auditada`, que ademas
 * rechaza con 404 a quien no sea optometrista y deja el intento en la bitacora.
 * A eso se suma `RecetaPolicy`. Son dos capas a proposito: si alguien agrega
 * una ruta nueva y olvida una, la otra sigue. Ver docs/07.
 */
class RecetaController extends Controller
{
    public function __construct(private readonly CalculadoraDeVencimiento $calculadora) {}

    public function index(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', Receta::class);

        $filtros = $peticion->query();

        $consulta = Receta::query()->with(['paciente', 'optometrista']);

        if ($filtros->filled('paciente_id')) {
            $consulta->where('paciente_id', $filtros->string('paciente_id'));
        }

        if ($filtros->filled('estado')) {
            $consulta->where('estado', $filtros->string('estado'));
        }

        /*
        | `solo_vencidas` y `solo_sin_plazo` no son un filtro de SQL cualquiera:
        | "vencida" se CALCULA contra la fecha de hoy (ver
        | App\Services\Recetas\CalculadoraDeVencimiento), asi que la consulta
        | se trae las que tienen fecha vencida y las filtra despues.
        */
        if ($filtros->boolean('solo_sin_plazo')) {
            $consulta->whereNull('fecha_vencimiento');
        } elseif ($filtros->boolean('solo_vencidas')) {
            $consulta->vencidas();
        }

        $porPagina = min(max((int) $filtros->input('por_pagina', 50), 1), 200);

        $pagina = $consulta
            ->orderByDesc('fecha_emision')
            ->orderByDesc('created_at')
            ->paginate($porPagina);

        return response()->json([
            'datos' => collect($pagina->items())
                ->map(fn (Receta $receta): array => $this->resumen($receta))
                ->all(),
            'paginacion' => [
                'pagina' => $pagina->currentPage(),
                'por_pagina' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
        ]);
    }

    public function store(EmitirRecetaRequest $peticion): JsonResponse
    {
        $this->authorize('create', Receta::class);

        /*
        | La fecha de vencimiento NO se inventa.
        |
        | Si no viene, se deja en NULL y el sistema no vence nada por su cuenta.
        | Ver docs/06.
        */
        $receta = DB::transaction(function () use ($peticion): Receta {
            return Receta::query()->create([
                ...$peticion->safe()->only([
                    'paciente_id',
                    'numero',
                    'fecha_emision',
                    'distancia_interpupilar_mm',
                    'observaciones',
                ]),
                'optometrista_id' => $peticion->user()->id,
                'fecha_vencimiento' => $peticion->input('fecha_vencimiento') ?: null,
                'motivo_sin_vencimiento' => $peticion->input('motivo_sin_vencimiento'),
                'estado' => EstadoReceta::Emitida->value,
            ]);
        });

        return response()->json($this->completo($receta), 201);
    }

    public function show(Receta $receta): JsonResponse
    {
        $this->authorize('view', $receta);

        return response()->json($this->completo($receta));
    }

    /**
     * Anular una receta.
     *
     * No se borra ni se edita: una receta emitida es un documento. Anular deja
     * constancia de que existio, y la deja fuera de los pedidos nuevos.
     */
    public function anular(Request $peticion, Receta $receta): JsonResponse
    {
        $this->authorize('anular', $receta);

        $receta->estado = EstadoReceta::Anulada;
        $receta->save();

        return response()->json($this->completo($receta->fresh()));
    }

    public function graduaciones(Receta $receta): JsonResponse
    {
        $this->authorize('view', $receta);

        return response()->json([
            'graduaciones' => array_map(
                static fn (Graduacion $graduacion): array => $graduacion->aFilaLegible(),
                $receta->graduacionesOrdenadas(),
            ),
        ]);
    }

    /**
     * Cargar o reemplazar las graduaciones de una receta.
     *
     * Se reciben las cuatro filas (dos ojos por dos meridianos) y se escriben
     * en una transaccion. El indice unico `(receta_id, ojo, meridiano)` impide
     * duplicados, y las restricciones CHECK de la tabla impiden que una fila
     * quede mal formada.
     */
    public function guardarGraduaciones(GuardarGraduacionesRequest $peticion, Receta $receta): JsonResponse
    {
        $this->authorize('update', $receta);

        DB::transaction(function () use ($peticion, $receta): void {
            $receta->graduaciones()->delete();

            foreach ($peticion->validated('graduaciones') as $fila) {
                Graduacion::query()->create([
                    'receta_id' => $receta->id,
                    'ojo' => $fila['ojo'],
                    'meridiano' => $fila['meridiano'],
                    'esfera' => $fila['esfera'],
                    'cilindro' => $fila['cilindro'],
                    'eje' => $fila['eje'],
                    // La adicion va solo en el meridiano de cerca. La validacion
                    // lo exige y la base lo prohibe en el de lejos.
                    'adiccion' => ($fila['meridiano'] ?? '') === 'cerca'
                        ? ($fila['adiccion'] ?? null)
                        : null,
                ]);
            }
        });

        return response()->json($this->completo($receta->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function resumen(Receta $receta): array
    {
        return [
            'id' => $receta->id,
            'numero' => $receta->numero,
            'paciente_id' => $receta->paciente_id,
            'paciente' => $receta->paciente?->nombreCompleto(),
            'fecha_emision' => $receta->fecha_emision->format('Y-m-d'),
            'estado' => $receta->estado->value,
            'estado_etiqueta' => $receta->estado->etiqueta(),
            'vencimiento' => $this->calculadora->calcular($receta)->aArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function completo(Receta $receta): array
    {
        $datos = $this->resumen($receta);

        $datos['optometrista_id'] = $receta->optometrista_id;
        $datos['optometrista'] = $receta->optometrista?->nombre;
        $datos['fecha_vencimiento'] = $receta->fecha_vencimiento?->format('Y-m-d');
        $datos['motivo_sin_vencimiento'] = $receta->motivo_sin_vencimiento;
        $datos['distancia_interpupilar_mm'] = $receta->distancia_interpupilar_mm;
        $datos['observaciones'] = $receta->observaciones;

        $datos['graduaciones'] = array_map(
            static fn (Graduacion $graduacion): array => $graduacion->aFilaLegible(),
            $receta->graduacionesOrdenadas(),
        );

        return $datos;
    }
}