<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarArmazonRequest;
use App\Models\Armazon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catalogo de armazones.
 *
 * Lo ve todo el mundo que trabaja en el local, porque es un dato comercial: es
 * lo que se le muestra al cliente en el mostrador. Editarlo es tarea de
 * administracion (ver ArmazonPolicy).
 *
 * LO QUE NO HAY
 * -------------
 * Ni precio, ni stock, ni proveedor. Esta primera version no hace
 * inventario: hace recetas, armazones, lentes, pedidos, calidad, entregas y
 * ajustes. Agregar las otras tres es otra historia, y se dice en docs/10.
 */
class ArmazonController extends Controller
{
    public function index(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', Armazon::class);

        $porPagina = min(max((int) $peticion->query('por_pagina', 50), 1), 200);

        $consulta = Armazon::query();

        if (! $peticion->boolean('incluir_inactivos')) {
            $consulta->activos();
        }

        $pagina = $consulta
            ->buscar((string) $peticion->query('q', ''))
            ->orderBy('nombre')
            ->paginate($porPagina);

        return response()->json([
            'datos' => collect($pagina->items())
                ->map(static fn (Armazon $armazon): array => self::fila($armazon))
                ->all(),
            'paginacion' => [
                'pagina' => $pagina->currentPage(),
                'por_pagina' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
        ]);
    }

    public function store(GuardarArmazonRequest $peticion): JsonResponse
    {
        $this->authorize('create', Armazon::class);

        $armazon = Armazon::query()->create($peticion->validated());

        return response()->json(self::fila($armazon), 201);
    }

    public function show(Armazon $armazon): JsonResponse
    {
        $this->authorize('view', $armazon);

        return response()->json(self::fila($armazon));
    }

    public function update(GuardarArmazonRequest $peticion, Armazon $armazon): JsonResponse
    {
        $this->authorize('update', $armazon);

        $armazon->fill($peticion->validated())->save();

        return response()->json(self::fila($armazon->fresh()));
    }

    /**
     * Desactivar, no borrar.
     *
     * Un armazon que ya se pidio a un laboratorio no se borra: el pedido lo
     * referencia. Desactivarlo lo saca del catalogo de nuevas ventas y deja el
     * historial intacto.
     */
    public function desactivar(GuardarArmazonRequest $peticion, Armazon $armazon): JsonResponse
    {
        $this->authorize('update', $armazon);

        $armazon->activo = $peticion->boolean('activo');
        $armazon->save();

        return response()->json(self::fila($armazon->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    public static function fila(Armazon $armazon): array
    {
        $medida = static fn (?float $valor): ?string => $valor === null
            ? null
            : number_format($valor, 2, ',', '.');

        return [
            'id' => $armazon->id,
            'codigo' => $armazon->codigo,
            'nombre' => $armazon->nombre,
            'marca' => $armazon->marca,
            'modelo' => $armazon->modelo,
            'material' => $armazon->material,
            'color' => $armazon->color,
            'ancho_varilla_mm' => $armazon->ancho_varilla_mm,
            'puente_mm' => $armazon->puente_mm,
            'alto_mm' => $armazon->alto_mm,
            // Se manda el ancho de caja ya calculado: es el numero con el que
            // arranca la verificacion de compatibilidad.
            'ancho_de_caja_mm' => $armazon->anchoDeCaja(),
            'medidas_legibles' => [
                'ancho_varilla_mm' => $medida((float) $armazon->ancho_varilla_mm),
                'puente_mm' => $medida((float) $armazon->puente_mm),
                'alto_mm' => $medida((float) $armazon->alto_mm),
                'ancho_de_caja_mm' => $medida($armazon->anchoDeCaja()),
            ],
            'activo' => $armazon->activo,
        ];
    }
}