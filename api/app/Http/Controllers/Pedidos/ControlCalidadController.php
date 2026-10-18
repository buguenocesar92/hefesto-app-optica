<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pedidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarControlCalidadRequest;
use App\Models\ControlCalidad;
use App\Models\PedidoLaboratorio;
use App\Services\Calidad\ComparadorDeGraduaciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Control de calidad al recibir un pedido.
 *
 * ESTE ES EL MOMENTO QUE EVITA EL RECLAMO
 * --------------------------------------
 * Si el laboratorio manda la graduacion equivocada, hay que enterarse antes de
 * entregarle los lentes al cliente. Despues no hay reclamo que solver: el
 * cliente ya se los puso.
 *
 * LAS DIFERENCIAS LAS CALCULA EL SERVIDOR
 * ---------------------------------------
 * No vienen en el cuerpo de la peticion. Se comparan con
 * App\Services\Calidad\ComparadorDeGraduaciones, valor por valor, contra la
 * foto que se guardo en `pedido_items.graduacion_pedida`. Si el navegador
 * mandara las suyas, un formulario alterado podria declarar conforme un pedido
 * que llego con la graduacion equivocada: exactamente lo que este registro
 * existe para evitar.
 *
 * EL VEREDICTO LO PONE UNA PERSONA
 * -------------------------------
 * El sistema dice que valores no cuadran; no dice si la diferencia se acepta.
 * La tolerancia nace en `null` porque es una decision clinica. Ver docs/06.
 */
class ControlCalidadController extends Controller
{
    public function __construct(private readonly ComparadorDeGraduaciones $comparador) {}

    public function index(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', ControlCalidad::class);

        $porPagina = min(max((int) $peticion->query('por_pagina', 50), 1), 200);

        $pagina = ControlCalidad::query()
            ->with(['pedido.paciente', 'revisor'])
            ->orderByDesc('fecha_revision')
            ->paginate($porPagina);

        return response()->json([
            'datos' => collect($pagina->items())
                ->map(fn (ControlCalidad $control): array => $this->fila($control))
                ->all(),
            'paginacion' => [
                'pagina' => $pagina->currentPage(),
                'por_pagina' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
        ]);
    }

    public function show(ControlCalidad $control): JsonResponse
    {
        $this->authorize('view', $control);

        return response()->json($this->fila($control));
    }

    public function store(RegistrarControlCalidadRequest $peticion): JsonResponse
    {
        $this->authorize('create', ControlCalidad::class);

        $pedido = PedidoLaboratorio::query()
            ->with('items')
            ->findOrFail($peticion->string('pedido_id'));

        if ($pedido->controlCalidad()->exists()) {
            return response()->json([
                'mensaje' => 'Este pedido ya tiene un control de calidad. '
                    .'Si el resultado fue no conforme, la correccion es un pedido nuevo.',
            ], 409);
        }

        $diferencias = $this->comparador->compararItems(
            $pedido->items
                ->map(static fn ($item): array => $item->paraComparar())
                ->all()
        );

        $control = DB::transaction(fn (): ControlCalidad => ControlCalidad::query()->create([
            'pedido_laboratorio_id' => $pedido->id,
            'revisado_por_usuario_id' => $peticion->user()->id,
            'resultado' => $peticion->string('resultado'),
            'fecha_revision' => $peticion->date('fecha_revision'),
            'diferencias' => $diferencias,
            'observaciones' => $peticion->input('observaciones'),
        ]));

        return response()->json($this->fila($control), 201);
    }

    /**
     * Compara sin guardar. Para revisar antes de decidir el veredicto.
     *
     * La ruta es util: el optometrista quiere ver las diferencias contra lo
     * pedido antes de poner conforme o no conforme, no al contras.
     */
    public function comparar(PedidoLaboratorio $pedido): JsonResponse
    {
        $this->authorize('view', $pedido);

        $diferencias = $this->comparador->compararItems(
            $pedido->items
                ->map(static fn ($item): array => $item->paraComparar())
                ->all()
        );

        return response()->json([
            'pedido_id' => $pedido->id,
            'diferencias' => $diferencias,
            'cantidad' => count($diferencias),

            /*
            | Si la tolerancia no esta configurada, el sistema NO dice si las
            | diferencias son aceptables. Solo las muestra. Ver docs/06.
            */
            'tolerancia_configurada' => config('optica.control_calidad.tolerancia_dioptrias') !== null,
            'veredicto_pendiente_de_decision' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(ControlCalidad $control): array
    {
        return [
            'id' => $control->id,
            'pedido_id' => $control->pedido_laboratorio_id,
            'pedido_codigo' => $control->pedido?->codigo,
            'resultado' => $control->resultado->value,
            'resultado_etiqueta' => $control->resultado->etiqueta(),
            'permite_entregar' => $control->resultado->permiteEntregar(),
            'fecha_revision' => $control->fecha_revision->format('Y-m-d'),
            'revisado_por' => $control->revisor?->nombre,
            'diferencias' => $control->diferencias ?? [],
            'diferencias_por_ojo' => $control->diferenciasPorOjo(),
            'cantidad_diferencias' => $control->cantidadDeDiferencias(),
            'observaciones' => $control->observaciones,

            /*
            | Si el veredicto no permite entregar, el pedido queda bloqueado
            | para la entrega. Este es el campo que hace que el reclamo no
            | llegue a pasar.
            */
            'pedido_bloqueado_para_entrega' => ! $control->resultado->permiteEntregar(),
        ];
    }
}