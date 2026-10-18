<?php

declare(strict_types=1);

namespace App\Http\Controllers\Entregas;

use App\Enums\EstadoAjuste;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarAjusteRequest;
use App\Models\Ajuste;
use App\Models\Entrega;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ajustes posteriores a la entrega.
 *
 * LO QUE SOSTIENE LA RELACION CON EL CLIENTE
 * -------------------------------------------
 * Todo lente se ajusta. Un armazon que aprieta o que se desliza no es un
 * defecto de fabricacion: es una entrega que no termino.
 *
 * EL MOTIVO ES EL DATO MAS UTIL
 * ----------------------------
 * Mas que el resultado. Saber que se ajustan mal las patillas, o que el
 * armazon elegido quedo chico para la cara del cliente, sirve para el
 * catalogo y para el siguiente pedido de esa persona. Por eso `motivo` es
 * texto y no una lista cerrada.
 *
 * NO HAY PLAZO DE GARANTIA QUE CUMPLIR
 * -----------------------------------
 * El ajuste se registra en cualquier momento. Los dias de garantia sugeridos
 * estan en `config('optica.entrega.dias_de_garantia_sugeridos')` y NACEN EN
 * NULL porque son una decision comercial, no un plazo legal verificable. Ver
 * docs/07.
 */
class AjusteController extends Controller
{
    public function index(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', Ajuste::class);

        $porPagina = min(max((int) $peticion->query('por_pagina', 50), 1), 200);

        $consulta = Ajuste::query()->with('entrega.paciente');

        if ($peticion->boolean('solo_abiertos')) {
            $consulta->abiertos();
        }

        $pagina = $consulta
            ->orderByDesc('fecha')
            ->paginate($porPagina);

        return response()->json([
            'datos' => collect($pagina->items())
                ->map(static fn (Ajuste $ajuste): array => self::fila($ajuste))
                ->all(),
            'paginacion' => [
                'pagina' => $pagina->currentPage(),
                'por_pagina' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],

            /*
            | El motivo mas frecuente. Es el dato que mas le sirve a la optica:
            | si casi todos los ajustes son por lo mismo, el problema es del
            | armazon que se eligio, no del lente.
            */
            'motivo_mas_frecuente' => Ajuste::motivoMasFrecuente(),
            'motivos_sugeridos' => RegistrarAjusteRequest::motivosSugeridos(),
        ]);
    }

    public function store(RegistrarAjusteRequest $peticion): JsonResponse
    {
        $this->authorize('create', Ajuste::class);

        $entrega = Entrega::query()->findOrFail($peticion->string('entrega_id'));

        if (! $entrega->admiteAjuste()) {
            return response()->json([
                'mensaje' => 'Solo se ajustan entregas ya hechas. '
                    .'Esta entrega todavia no fue entregada.',
            ], 409);
        }

        $ajuste = Ajuste::query()->create([
            'entrega_id' => $entrega->id,
            'fecha' => $peticion->date('fecha'),
            'motivo' => $peticion->string('motivo'),
            'descripcion' => $peticion->input('descripcion'),
            'estado' => $peticion->input('estado', EstadoAjuste::Pendiente->value),
            'lente_sustituido' => $peticion->boolean('lente_sustituido'),
            'observaciones' => $peticion->input('observaciones'),
        ]);

        return response()->json(self::fila($ajuste), 201);
    }

    /**
     * Cerrar un ajuste.
     *
     * Un ajuste ya realizado no se edita: queda como quedo. Eso es lo que
     * hace que el historial de ajustes sirva para entender por que se cambia
     * tanto un armazon o un lente.
     */
    public function cerrar(Request $peticion, Ajuste $ajuste): JsonResponse
    {
        $this->authorize('update', $ajuste);

        $ajuste->estado = EstadoAjuste::Realizado;

        $lenteSustituido = $peticion->input('lente_sustituido');

        if ($lenteSustituido !== null) {
            $ajuste->lente_sustituido = (bool) $lenteSustituido;
        }

        $ajuste->save();

        return response()->json(self::fila($ajuste));
    }

    /**
     * @return array<string, mixed>
     */
    public static function fila(Ajuste $ajuste): array
    {
        return [
            'id' => $ajuste->id,
            'entrega_id' => $ajuste->entrega_id,
            'paciente' => $ajuste->paciente()?->nombreCompleto(),
            'fecha' => $ajuste->fecha->format('Y-m-d'),
            'motivo' => $ajuste->motivo,
            'descripcion' => $ajuste->descripcion,
            'estado' => $ajuste->estado->value,
            'estado_etiqueta' => $ajuste->estado->etiqueta(),
            'lente_sustituido' => $ajuste->lente_sustituido,
            'observaciones' => $ajuste->observaciones,
        ];
    }
}