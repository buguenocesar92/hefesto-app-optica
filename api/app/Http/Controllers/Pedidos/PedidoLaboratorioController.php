<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pedidos;

use App\Enums\EstadoPedido;
use App\Enums\Ojo;
use App\Enums\RolOptica;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarPedidoRequest;
use App\Http\Requests\RecibirPedidoRequest;
use App\Models\Armazon;
use App\Models\Lente;
use App\Models\PedidoItem;
use App\Models\PedidoLaboratorio;
use App\Models\Receta;
use App\Services\Optica\VerificadorDeCompatibilidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos a laboratorio.
 *
 * LA COMPATIBILIDAD SE CALCULA ACA, NO SE RECIBE
 * ----------------------------------------------
 * Al crear un pedido se corre App\Services\Optica\VerificadorDeCompatibilidad
 * con el armazon y el lente que estan en la base, y el veredicto CON EL
 * DESGLOSE se guarda en el pedido.
 *
 * Se guarda aunque salga `no_verificable`, porque la parte geometrica del
 * calculo si se hizo y sirve para saber que falta. Lo que no se hace es
 * inventar un "compatible": si falta un dato, el pedido queda marcado como no
 * verificable y la persona decide. Ver docs/06.
 *
 * LA GRADUACION SE COPIA AL PEDIDO
 * -------------------------------
 * Cada item guarda una foto de la graduacion en `graduacion_pedida`. No una
 * referencia: una copia. Si la receta se corrige despues de enviado el pedido,
 * el control de calidad tiene que seguir comparando contra lo que realmente
 * salio de la optica. Ver docs/02.
 */
class PedidoLaboratorioController extends Controller
{
    public function index(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', PedidoLaboratorio::class);

        $filtros = $peticion->query();

        $consulta = PedidoLaboratorio::query()
            ->with(['paciente', 'armazon', 'lente', 'controlCalidad']);

        if ($filtros->filled('estado')) {
            $consulta->where('estado', $filtros->string('estado'));
        }

        if ($filtros->boolean('en_curso')) {
            $consulta->enCurso();
        }

        $porPagina = min(max((int) $filtros->input('por_pagina', 50), 1), 200);

        $pagina = $consulta
            ->orderByDesc('created_at')
            ->paginate($porPagina);

        return response()->json([
            'datos' => collect($pagina->items())
                ->map(fn (PedidoLaboratorio $pedido): array => $this->fila($pedido))
                ->all(),
            'paginacion' => [
                'pagina' => $pagina->currentPage(),
                'por_pagina' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
        ]);
    }

    public function store(GuardarPedidoRequest $peticion, VerificadorDeCompatibilidad $verificador): JsonResponse
    {
        $this->authorize('create', PedidoLaboratorio::class);

        $datos = $peticion->safe()->only([
            'codigo',
            'paciente_id',
            'receta_id',
            'armazon_id',
            'lente_id',
            'laboratorio',
            'fecha_envio',
            'notas',
        ]);

        $compatibilidad = null;

        if (! empty($datos['armazon_id']) && ! empty($datos['lente_id'])) {
            $armazon = Armazon::query()->findOrFail($datos['armazon_id']);
            $lente = Lente::query()->findOrFail($datos['lente_id']);

            $compatibilidad = $verificador->verificar($armazon, $lente);
        }

        $pedido = DB::transaction(function () use ($peticion, $datos, $compatibilidad): PedidoLaboratorio {
            $pedido = PedidoLaboratorio::query()->create([
                ...$datos,
                'fecha_prometida' => $peticion->input('fecha_prometida') ?: null,
                // De donde salio la promesa queda escrito, para que nadie asuma
                // que el sistema la invento si en realidad la puso una persona.
                'origen_fecha_prometida' => $peticion->origen(),
                'compatibilidad_resultado' => $compatibilidad?->veredicto->value,
                'compatibilidad_desglose' => $compatibilidad?->aArray(),
            ]);

            $this->crearItems($pedido, $peticion->input('receta_id'));

            return $pedido;
        });

        return response()->json($this->completo($pedido), 201);
    }

    public function show(PedidoLaboratorio $pedido): JsonResponse
    {
        $this->authorize('view', $pedido);

        return response()->json($this->completo($pedido));
    }

    public function update(GuardarPedidoRequest $peticion, PedidoLaboratorio $pedido): JsonResponse
    {
        $this->authorize('update', $pedido);

        $pedido->fill($peticion->safe()->only([
            'codigo',
            'armazon_id',
            'lente_id',
            'laboratorio',
            'fecha_envio',
            'fecha_prometida',
            'notas',
        ]))->save();

        return response()->json($this->completo($pedido->fresh()));
    }

    /**
     * Registrar que llego el pedido del laboratorio.
     *
     * Se guarda la graduacion RECIBIDA tal como viene. Si al copiar se
     * "arreglara" cualquier valor, el control de calidad no encontraria nada
     * y la revision seria un tramite. Ver RecibirPedidoRequest.
     */
    public function recibir(RecibirPedidoRequest $peticion, PedidoLaboratorio $pedido): JsonResponse
    {
        $this->authorize('update', $pedido);

        DB::transaction(function () use ($peticion, $pedido): void {
            foreach ($peticion->validated('items') as $item) {
                PedidoItem::query()
                    ->where('pedido_laboratorio_id', $pedido->id)
                    ->where('ojo', $item['ojo'])
                    ->update([
                        'graduacion_recibida' => $item['graduacion_recibida'] ?? null,
                    ]);
            }

            $pedido->fecha_recepcion = $peticion->date('fecha_recepcion');
            $pedido->estado = EstadoPedido::Recibido->value;
            $pedido->save();
        });

        return response()->json($this->completo($pedido->fresh()));
    }

    /**
     * Cancelar un pedido que todavia no llego.
     *
     * No se borra: un encargo enviado es un documento. Cancelarlo deja
     * constancia, y el cliente al que ya se le cobro no puede aparecer despues
     * con el mismo codigo.
     */
    public function cancelar(PedidoLaboratorio $pedido): JsonResponse
    {
        $this->authorize('cancelar', $pedido);

        $pedido->estado = EstadoPedido::Cancelado->value;
        $pedido->save();

        return response()->json($this->completo($pedido->fresh()));
    }

    /**
     * Crea un item por ojo, copiando la graduacion de la receta.
     */
    private function crearItems(PedidoLaboratorio $pedido, ?string $recetaId): void
    {
        if ($recetaId === null || $recetaId === '') {
            return;
        }

        $receta = Receta::query()->with('graduaciones')->find($recetaId);

        if ($receta === null) {
            return;
        }

        foreach (Ojo::ambos() as $ojo) {
            $graduaciones = $receta->graduaciones
                ->where('ojo', $ojo->value)
                ->keyBy('meridiano->value');

            if ($graduaciones->isEmpty()) {
                continue;
            }

            /*
            | La foto de lo que se pidio, por meridiano. Se arma antes de
            | guardar para que quede fija aunque la receta cambie despues.
            */
            $foto = [];

            foreach ($graduaciones as $meridiano => $graduacion) {
                $foto[(string) $meridiano] = $graduacion->aComponentes();
            }

            PedidoItem::query()->create([
                'pedido_laboratorio_id' => $pedido->id,
                'ojo' => $ojo->value,
                'lente_id' => $pedido->lente_id,
                'cantidad' => 1,
                'graduacion_pedida' => $foto,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(PedidoLaboratorio $pedido): array
    {
        return [
            'id' => $pedido->id,
            'codigo' => $pedido->codigo,
            'paciente_id' => $pedido->paciente_id,
            'paciente' => $pedido->paciente?->nombreCompleto(),
            'estado' => $pedido->estado->value,
            'estado_etiqueta' => $pedido->estado->etiqueta(),
            'laboratorio' => $pedido->laboratorio,
            'fecha_prometida' => $pedido->fecha_prometida?->format('Y-m-d'),
            'origen_fecha_prometida' => $pedido->origen_fecha_prometida,
            'esta_atrasado' => $pedido->estaAtrasado(),
            'dias_para_promesa' => $pedido->diasParaPromesa(),
            'compatibilidad' => [
                'resultado' => $pedido->compatibilidad_resultado?->value,
                'etiqueta' => $pedido->compatibilidad_resultado?->etiqueta(),
            ],
            'puede_entregarse' => $pedido->puedeEntregarse(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function completo(PedidoLaboratorio $pedido): array
    {
        $datos = $this->fila($pedido);

        $datos['receta_id'] = $pedido->receta_id;
        $datos['armazon_id'] = $pedido->armazon_id;
        $datos['lente_id'] = $pedido->lente_id;
        $datos['fecha_envio'] = $pedido->fecha_envio?->format('Y-m-d');
        $datos['fecha_recepcion'] = $pedido->fecha_recepcion?->format('Y-m-d');
        $datos['notas'] = $pedido->notas;
        $datos['compatibilidad']['desglose'] = $pedido->compatibilidad_desglose;

        /*
        | La graduacion pedida SOLO se manda si quien mira puede leerla.
        | El endpoint de pedidos lo abre el mostrador, asi que sin esta
        | condicion le llegaria la graduacion del paciente sin haber pasado por
        | `RecetaPolicy`. El filtro va aca y no en la ruta porque el mostrador
        | SI necesita ver el pedido: codigo, estado y fecha prometida.
        */
        $puedeVerGraduaciones = in_array(
            auth()->user()?->rol,
            RolOptica::conAccesoAGraduaciones(),
            true
        );

        $datos['items'] = $pedido->items()
            ->get()
            ->map(function (PedidoItem $item) use ($puedeVerGraduaciones): array {
                $fila = [
                    'id' => $item->id,
                    'ojo' => $item->ojo->value,
                    'ojo_etiqueta' => $item->ojo->etiqueta(),
                    'lente_id' => $item->lente_id,
                    'cantidad' => $item->cantidad,
                    'tiene_graduacion_recibida' => $item->tieneGraduacionRecibida(),
                ];

                if ($puedeVerGraduaciones) {
                    $fila['graduacion_pedida'] = $item->graduacion_pedida;
                    $fila['graduacion_recibida'] = $item->graduacion_recibida;
                }

                return $fila;
            })
            ->all();

        return $datos;
    }
}