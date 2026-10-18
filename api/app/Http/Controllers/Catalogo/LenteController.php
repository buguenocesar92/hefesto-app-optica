<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarLenteRequest;
use App\Models\Armazon;
use App\Models\Lente;
use App\Services\Optica\VerificadorDeCompatibilidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catalogo de lentes y verificacion de compatibilidad con el armazon.
 *
 * LA VERIFICACION DE COMPATIBILIDAD
 * --------------------------------
 * Es la comprobacion que sostiene el producto: si el diametro de la pastilla
 * del lente no cubre la caja del armazon mas el borde, la hecha no sirve.
 *
 * Devuelve el DESGLOSE DEL CALCULO, no un si o un no. Y cuando falta un dato
 * devuelve `no_verificable` con la parte geometrica ya calculada, en vez de
 * inventar un veredicto. Ver App\Services\Optica\VerificadorDeCompatibilidad y
 * docs/06.
 */
class LenteController extends Controller
{
    public function index(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', Lente::class);

        $porPagina = min(max((int) $peticion->query('por_pagina', 50), 1), 200);

        $consulta = Lente::query();

        if (! $peticion->boolean('incluir_inactivos')) {
            $consulta->activos();
        }

        $pagina = $consulta
            ->buscar((string) $peticion->query('q', ''))
            ->orderBy('nombre')
            ->paginate($porPagina);

        return response()->json([
            'datos' => collect($pagina->items())
                ->map(static fn (Lente $lente): array => self::fila($lente))
                ->all(),
            'paginacion' => [
                'pagina' => $pagina->currentPage(),
                'por_pagina' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
        ]);
    }

    public function store(GuardarLenteRequest $peticion): JsonResponse
    {
        $this->authorize('create', Lente::class);

        $lente = Lente::query()->create($peticion->validated());

        return response()->json(self::fila($lente), 201);
    }

    public function show(Lente $lente): JsonResponse
    {
        $this->authorize('view', $lente);

        return response()->json(self::fila($lente));
    }

    public function update(GuardarLenteRequest $peticion, Lente $lente): JsonResponse
    {
        $this->authorize('update', $lente);

        $lente->fill($peticion->validated())->save();

        return response()->json(self::fila($lente->fresh()));
    }

    public function desactivar(GuardarLenteRequest $peticion, Lente $lente): JsonResponse
    {
        $this->authorize('update', $lente);

        $lente->activo = $peticion->boolean('activo');
        $lente->save();

        return response()->json(self::fila($lente->fresh()));
    }

    /**
     * Verifica si un armazon y un lente encajan.
     *
     * No muta nada: es una consulta. La autorizacion va contra el armazon con
     * `ArmazonPolicy::view`, que basta con tener una cuenta activa. La
     * administracion la necesita para medir antes de cargar el catalogo.
     *
     * Los ids vienen de la peticion y el servicio los busca por su cuenta. La
     * RLS se encarga de que solo se vean los de esta optica.
     */
    public function verificarCompatibilidad(
        Request $peticion,
        VerificadorDeCompatibilidad $verificador,
    ): JsonResponse {
        $armazon = Armazon::query()->findOrFail($peticion->string('armazon_id'));
        $lente = Lente::query()->findOrFail($peticion->string('lente_id'));

        $this->authorize('view', $armazon);

        return response()->json($verificador->verificar($armazon, $lente)->aArray());
    }

    /**
     * @return array<string, mixed>
     */
    public static function fila(Lente $lente): array
    {
        return [
            'id' => $lente->id,
            'codigo' => $lente->codigo,
            'nombre' => $lente->nombre,
            'marca' => $lente->marca,
            'material' => $lente->material,
            'indice_refraccion' => $lente->indice_refraccion,
            'tratamientos' => $lente->tratamientos ?? [],
            'diametro_corte_mm' => $lente->diametro_corte_mm,
            'es_progresivo' => $lente->es_progresivo,
            'activo' => $lente->activo,

            /*
            | Que le falta al registro.
            |
            | La interfaz lo muestra como aviso. No bloquea nada: es mejor
            | tener un lente en el catalogo sin indice que no tenerlo.
            */
            'datos_incompletos' => $lente->datosIncompletos(),
            'puede_verificar_compatibilidad' => $lente->tieneDiametroDeCorte(),
        ];
    }
}