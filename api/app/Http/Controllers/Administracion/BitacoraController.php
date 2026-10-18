<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administracion;

use App\Http\Controllers\Controller;
use App\Models\AccesoReceta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consulta de la bitacora de accesos a recetas.
 *
 * SOLO ADMINISTRACION, POR EL MIDDLEWARE
 * --------------------------------------
 * Este controlador cuelga del grupo `Route::middleware('admin')` en
 * routes/web.php, asi que el rol ya se comprobo antes de llegar aca. No hace
 * falta una policy propia: si el middleware falla, responde 404 y el
 * controlador ni se ejecuta.
 *
 * LO QUE SE MUESTRA
 * -----------------
 * Quien, cuando, sobre que recurso, y si se le permitio. NUNCA el contenido
 * de la receta ni ningun valor de graduacion: la bitacora no puede convertirse
 * en una segunda copia de la informacion de salud sin proteccion. Ver docs/07.
 *
 * LO QUE NO HACE
 * -------------
 * No borra nada. Los dias de conservacion estan en
 * `config('optica.retencion.accesos_recetas_dias')` y NACEN EN NULL: no se
 * pudo verificar que plazo corresponde. Con la variable vacia la aplicacion no
 * purga nada por su cuenta.
 */
class BitacoraController extends Controller
{
    public function index(Request $peticion): JsonResponse
    {
        $filtros = $peticion->query();

        $consulta = AccesoReceta::query()->with('usuario');

        if ($filtros->filled('usuario_id')) {
            $consulta->where('usuario_id', $filtros->string('usuario_id'));
        }

        if ($filtros->filled('recurso')) {
            $consulta->where('recurso', $filtros->string('recurso'));
        }

        if ($filtros->has('permitido')) {
            $consulta->where('permitido', $filtros->boolean('permitido'));
        }

        $porPagina = min(max((int) $filtros->input('por_pagina', 50), 1), 200);

        $pagina = $consulta
            ->orderByDesc('ocurrido_en')
            ->paginate($porPagina);

        return response()->json([
            'datos' => collect($pagina->items())
                ->map(static fn (AccesoReceta $acceso): array => [
                    'id' => $acceso->id,
                    'usuario_id' => $acceso->usuario_id,
                    'usuario' => $acceso->usuario?->nombre,
                    'recurso' => $acceso->recurso,
                    'recurso_id' => $acceso->recurso_id,
                    'accion' => $acceso->accion,
                    'permitido' => $acceso->permitido,
                    'motivo' => $acceso->motivo,
                    'ip' => $acceso->ip,
                    'ocurrido_en' => $acceso->created_at?->format('Y-m-d H:i:s'),
                ])
                ->all(),
            'paginacion' => [
                'pagina' => $pagina->currentPage(),
                'por_pagina' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],

            /*
            | Umbrales de revision, de `config/optica.php`. Son heuristicas de
            | revision, no deteccion de incidentes.
            */
            'umbrales' => [
                'denegados_por_hora' => (int) config('optica.alerta_denegados_por_hora'),
                'denegados_por_usuario' => (int) config('optica.alerta_denegados_por_usuario'),
            ],
        ]);
    }
}