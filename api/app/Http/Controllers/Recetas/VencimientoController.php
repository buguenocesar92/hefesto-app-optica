<?php

declare(strict_types=1);

namespace App\Http\Controllers\Recetas;

use App\Http\Controllers\Controller;
use App\Models\Receta;
use App\Services\Recetas\CalculadoraDeVencimiento;
use App\Services\Recetas\Vencimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consulta de vencimientos.
 *
 * PARA QUIEN NO PUEDE VER GRADUACIONES
 * ------------------------------------
 * Esta ruta NO lleva el middleware `receta.auditada`, porque no abre ninguna
 * receta: cuenta. Muestra cuantos y cuando vencen, y eso es informacion de
 * agenda, no de salud.
 *
 * El detalle (el paciente, el numero, la graduacion) SI requiere
 * `RecetaPolicy` y esta en `RecetaController`.
 *
 * LO QUE ESTA PENDIENTE SE DICE
 * ----------------------------
 * El indice devuelve tambien cuantas recetas NO tienen fecha de vencimiento y
 * si el plazo legal sigue sin verificarse. Es el numero que hace visible el
 * trabajo pendiente. Ver docs/06.
 */
class VencimientoController extends Controller
{
    public function __construct(private readonly CalculadoraDeVencimiento $calculadora) {}

    public function indice(Request $peticion): JsonResponse
    {
        $diasAviso = (int) config('optica.receta.dias_de_aviso', 30);

        $vencidas = Receta::query()
            ->where('estado', 'emitida')
            ->vencidas()
            ->count();

        $porVencer = Receta::query()
            ->where('estado', 'emitida')
            ->porVencer()
            ->count();

        $sinPlazo = Receta::query()
            ->where('estado', 'emitida')
            ->sinVencimiento()
            ->count();

        $vigentes = Receta::query()
            ->where('estado', 'emitida')
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '>', now()->addDays($diasAviso)->toDateString())
            ->count();

        return response()->json([
            'dias_de_aviso' => $diasAviso,
            'vigentes' => $vigentes,
            'por_vencer' => $porVencer,
            'vencidas' => $vencidas,
            'sin_plazo' => $sinPlazo,

            /*
            | El estado de la CONFIGURACION, no de los datos.
            |
            | Si esto va en `true`, el sistema no sabe cuanto vale una receta y
            | por lo tanto no puede decir si una esta vigente. Decirlo es la
            | diferencia entre "no tenemos recetas vencidas" y "no sabemos si hay
            | recetas vencidas".
            */
            'plazo_legal_pendiente_de_verificacion' => ! $this->calculadora->hayAlgunPlazoConfigurado(),

            'franjas' => $this->calculadora->franjas(),
        ]);
    }

    /**
     * Explicacion en texto del estado de una receta concreta.
     *
     * Lo usa la interfaz para el aviso junto al numero de receta.
     *
     * @return array<string, mixed>
     */
    public function explicar(Receta $receta): JsonResponse
    {
        $this->authorize('view', $receta);

        $vencimiento = $this->calculadora->calcular($receta);

        return response()->json([
            'vencimiento' => $vencimiento->aArray(),
            'puede_despachar' => $vencimiento->permiteDespachar(),
            'edad_anios' => Vencimiento::edadEnAnios(
                $receta->paciente?->fecha_nacimiento,
                now()
            ),
        ]);
    }
}