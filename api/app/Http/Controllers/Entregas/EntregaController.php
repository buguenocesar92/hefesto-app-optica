<?php

declare(strict_types=1);

namespace App\Http\Controllers\Entregas;

use App\Enums\EstadoEntrega;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarEntregaRequest;
use App\Models\Ajuste;
use App\Models\Entrega;
use App\Models\PedidoLaboratorio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Entrega de lentes al cliente.
 *
 * ENTREGAR NO ES EL FINAL
 * ----------------------
 * Un lente recien entregado casi nunca esta bien ajustado la primera vez. Por
 * eso la entrega tiene estado y por eso existe `ajustes` al lado.
 *
 * LA ENTREGA EXIGE CONTROL DE CALIDAD
 * -----------------------------------
 * `EntregaPolicy::entregar` no devuelve falso por un capricho: devuelve falso
 * si el pedido no tiene control de calidad, o si el control salio
 * `no_conforme`. Es la comprobacion que evita que al cliente le lleguen
 * lentes con la graduacion que el laboratorio mando equivocada.
 *
 * ENTRAR SIENDO 404, NO 403
 * ------------------------
* El rechazo de `entregar` responde 404 (ver bootstrap/app.php). Un 403
 * confirmaria que el pedido existe y que esta en una situacion rara, que es
 * justo la informacion que no hay que dar.
 */
class EntregaController extends Controller
{
    public function index(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', Entrega::class);

        $porPagina = min(max((int) $peticion->query('por_pagina', 50), 1), 200);

        $pagina = Entrega::query()
            ->with(['paciente', 'pedido', 'ajustes'])
            ->orderByDesc('fecha_registro')
            ->paginate($porPagina);

        return response()->json([
            'datos' => collect($pagina->items())
                ->map(fn (Entrega $entrega): array => $this->fila($entrega))
                ->all(),
            'paginacion' => [
                'pagina' => $pagina->currentPage(),
                'por_pagina' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
        ]);
    }

    public function store(RegistrarEntregaRequest $peticion): JsonResponse
    {
        $this->authorize('create', Entrega::class);

        $pedido = PedidoLaboratorio::query()->findOrFail($peticion->string('pedido_id'));

        if ($pedido->entrega()->exists()) {
            return response()->json([
                'mensaje' => 'Este pedido ya tiene una entrega registrada.',
            ], 409);
        }

        $entrega = Entrega::query()->create([
            'pedido_laboratorio_id' => $pedido->id,
            'paciente_id' => $pedido->paciente_id,
            'estado' => $peticion->input('fecha_entrega')
                ? EstadoEntrega::Entregada->value
                : EstadoEntrega::Registrada->value,
            'fecha_registro' => $peticion->date('fecha_registro'),
            'fecha_entrega' => $peticion->input('fecha_entrega') ?: null,
            'recibido_por' => $peticion->input('recibido_por'),
            'observaciones' => $peticion->input('observaciones'),
        ]);

        return response()->json($this->fila($entrega), 201);
    }

    /**
     * Marcar la entrega como hecha.
     *
     * Aqui es donde se comprueba el control de calidad. Si el pedido salio
     * `no_conforme`, esta ruta devuelve 404 y el lente no se entrega. Es el
     * momento en que se evita el reclamo.
     */
    public function entregar(Request $peticion, Entrega $entrega): JsonResponse
    {
        $this->authorize('entregar', $entrega);

        DB::transaction(function () use ($peticion, $entrega): void {
            $entrega->estado = EstadoEntrega::Entregada;
            $entrega->fecha_entrega = now()->toDateString();

            $recibidoPor = $peticion->input('recibido_por');

            if (is_string($recibidoPor) && $recibidoPor !== '') {
                $entrega->recibido_por = $recibidoPor;
            }

            $entrega->save();
        });

        return response()->json($this->fila($entrega->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(Entrega $entrega): array
    {
        return [
            'id' => $entrega->id,
            'pedido_id' => $entrega->pedido_laboratorio_id,
            'pedido_codigo' => $entrega->pedido?->codigo,
            'paciente_id' => $entrega->paciente_id,
            'paciente' => $entrega->paciente?->nombreCompleto(),
            'estado' => $entrega->estado->value,
            'estado_etiqueta' => $entrega->estado->etiqueta(),
            'fecha_registro' => $entrega->fecha_registro->format('Y-m-d'),
            'fecha_entrega' => $entrega->fecha_entrega?->format('Y-m-d'),
            'dias_desde_entrega' => $entrega->diasDesdeEntrega(),
            'recibido_por' => $entrega->recibido_por,
            'observaciones' => $entrega->observaciones,
            'ajustes' => $entrega->ajustes()
                ->orderByDesc('fecha')
                ->get()
                ->map(static fn (Ajuste $ajuste): array => [
                    'id' => $ajuste->id,
                    'fecha' => $ajuste->fecha->format('Y-m-d'),
                    'motivo' => $ajuste->motivo,
                    'estado' => $ajuste->estado->value,
                    'lente_sustituido' => $ajuste->lente_sustituido,
                ])
                ->all(),
            'ajustes_abiertos' => $entrega->ajustes()->abiertos()->count(),
            'admite_ajuste' => $entrega->admiteAjuste(),
        ];
    }
}