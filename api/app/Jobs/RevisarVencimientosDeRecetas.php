<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Receta;
use App\Services\Recetas\CalculadoraDeVencimiento;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Revisa las recetas de una optica y separa las que hay que avisar.
 *
 * POR QUE VA EN COLA
 * ------------------
 * Revisar una optica entera son miles de filas y una consulta por receta,
 * porque el vencimiento se calcula (ver
 * App\Services\Recetas\CalculadoraDeVencimiento). Hacerlo en la peticion
 * seria lento; hacerlo en un comando sincrono bloquearia el despliegue.
 *
 * POR QUE RECORRE TODAS LAS RECETAS
 * ---------------------------------
 * Podría empezar por `fecha_vencimiento is not null and fecha_vencimiento
 * < hoy`, y sería más rápido. Pero la parte importante del trabajo es la OTRA:
 * contar las recetas que NO tienen fecha de vencimiento, que son las que el
 * sistema no puede decir si están vigentes.
 * QUE NO HACE
 * -----------
 * No modifica recetas. No vence nada. No manda correos. Solo cuenta y deja el
 * resultado en el registro.
 *
 * Es a proposito: con los plazos legales sin verificar, un trabajo
 * automatico que "venciera" recetas estaria haciendo exactamente lo que el
 * producto dice que no hace. Ver docs/06.
 *
 * AISLAMIENTO
 * -----------
 * El job corre en el worker, que reutiliza la misma conexion para trabajos de
 * opticas distintas. Por eso abre el contexto con `ejecutarAislado`, que fija
 * `app.tenant_id` con alcance de transaccion y lo descarta al salir. Sin eso,
 * una tarea de una optica podria leer graduaciones de otra. Ver docs/03.
 */
class RevisarVencimientosDeRecetas implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $opticaId) {}

    public function handle(CalculadoraDeVencimiento $calculadora): void
    {
        app(ContextoTenant::class)->ejecutarAislado(
            $this->opticaId,
            function () use ($calculadora): void {
                $total = Receta::query()->where('estado', 'emitida')->count();

                $sinPlazo = 0;
                $vencidas = 0;
                $porVencer = 0;
                $vigentes = 0;

                Receta::query()
                    ->where('estado', 'emitida')
                    ->with('paciente')
                    ->chunkById(200, function ($recetas) use (
                        $calculadora,
                        &$sinPlazo,
                        &$vencidas,
                        &$porVencer,
                        &$vigentes
                    ): void {
                        foreach ($recetas as $receta) {
                            match ($calculadora->calcular($receta)->estado) {
                                'sin_plazo' => $sinPlazo++,
                                'vencida' => $vencidas++,
                                'por_vencer' => $porVencer++,
                                default => $vigentes++,
                            };
                        }
                    });

                report(sprintf(
                    'optica %s: %d recetas emitidas; %d sin fecha de vencimiento, '
                    .'%d vencidas, %d por vencer, %d vigentes.',
                    $this->opticaId,
                    $total,
                    $sinPlazo,
                    $vencidas,
                    $porVencer,
                    $vigentes,
                ));
            }
        );
    }
}