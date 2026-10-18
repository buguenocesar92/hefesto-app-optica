<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Punto de entrada de los seeders.
 *
 * Para una instalacion de trabajo:
 *
 *     php artisan db:seed
 *
 * `OpticaDeEjemploSeeder` es idempotente: se puede correr mas de una vez sin
 * duplicar la optica ni las cuentas. Ver docs/04.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(OpticaDeEjemploSeeder::class);
    }
}