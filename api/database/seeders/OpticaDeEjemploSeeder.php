<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RolOptica;
use App\Models\Optica;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Datos minimos para mirar el sistema de punta a punta.
 *
 * QUE CREA
 * --------
 * Una optica y las tres cuentas de los tres roles.
 *
 * QUE NO CREA
 * -----------
 * Recetas, graduaciones, armazones ni lentes.
 *
 * Y esa omision es deliberada: las graduaciones son informacion de salud y los
 * plazos de vigencia son norma sanitaria que no se ha verificado. Sembrar
 * recetas de ejemplo obligaria a inventar plazos y graduaciones, y quedaria en
 * la base algo que parece real y no lo es. Quien vaya a mirar el sistema carga
 * su propio paciente y su propia receta. Ver docs/04.
 */
class OpticaDeEjemploSeeder extends Seeder
{
    public function run(): void
    {
        $optica = Optica::query()->firstOrCreate(
            ['correlacion_identidad' => 'centro'],
            [
                'nombre' => 'Optica de ejemplo',
                'slug' => 'optica-de-ejemplo',
                'zona_horaria' => 'America/Santiago',
                'activo' => true,
            ]
        );

        $cuentas = [
            ['nombre' => 'Administracion', 'correo' => 'admin@ejemplo.cl', 'rol' => RolOptica::Administrador],
            ['nombre' => 'Optometrista', 'correo' => 'optometrista@ejemplo.cl', 'rol' => RolOptica::Optometrista],
            ['nombre' => 'Mostrador', 'correo' => 'mostrador@ejemplo.cl', 'rol' => RolOptica::Mostrador],
        ];

        foreach ($cuentas as $cuenta) {
            Usuario::query()->firstOrCreate(
                ['tenant_id' => $optica->id, 'correo' => $cuenta['correo']],
                [
                    'nombre' => $cuenta['nombre'],
                    'rol' => $cuenta['rol']->value,
                    'activo' => true,
                ]
            );
        }

        $this->command?->info('Optica de ejemplo creada: '.$optica->nombre);
        $this->command?->warn(
            'Las cuentas son de ejemplo y NO tienen contrasena: la identidad se resuelve contra Authentik. '
            .'Solo entran si en Authentik pertenecen a los equipos correctos. Ver docs/04.'
        );
    }
}