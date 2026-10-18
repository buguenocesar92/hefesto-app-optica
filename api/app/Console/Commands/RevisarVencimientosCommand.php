<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RevisarVencimientosDeRecetas;
use App\Models\Optica;
use Illuminate\Console\Command;

/**
 * Lanza la revision de vencimientos de todas las opticas activas.
 *
 *     php artisan optica:revisar-vencimientos
 *
 * En una instalacion con una sola optica, el comando puede correr sincrónico
 * (ver routes/console.php). Con varias, lanza un trabajo por optica.
 *
 * QUE HACE Y QUE NO
 * -----------------
 * Cuenta. No vence recetas, no manda correos y no cambia nada. Los plazos
 * legales por franja etaria nacen en `null` y no se han verificado, asi que
 * dejar que un proceso automatico decida la vigencia de una receta seria
 * justamente el invento que el producto evita. Ver docs/06.
 */
class RevisarVencimientosCommand extends Command
{
    protected $signature = 'optica:revisar-vencimientos
        {--optica= : Revisa solo una optica, por correlacion_identidad.}
        {--sincrono : No encola el trabajo: revisa en el momento y muestra el resultado.}';

    protected $description = 'Revisa los vencimientos de las recetas y reporta cuantas hay de cada tipo.';

    public function handle(): int
    {
        $opticas = $this->opticas();

        if ($opticas === []) {
            $this->components->warn('No hay opticas activas que revisar.');

            return self::SUCCESS;
        }

        foreach ($opticas as $optica) {
            if ($this->option('sincrono')) {
                dispatch_sync(new RevisarVencimientosDeRecetas($optica->id));

                continue;
            }

            dispatch(new RevisarVencimientosDeRecetas($optica->id));

            $this->components->info('Revision encolada para '.$optica->nombre.'.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<Optica>
     */
    private function opticas(): array
    {
        $correlacion = $this->option('optica');

        $consulta = Optica::query()->where('activo', true);

        if (is_string($correlacion) && $correlacion !== '') {
            $consulta->where('correlacion_identidad', $correlacion);
        }

        return $consulta->get()->all();
    }
}