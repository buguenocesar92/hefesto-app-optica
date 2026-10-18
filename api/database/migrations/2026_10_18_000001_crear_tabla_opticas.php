<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de opticas: la EMPRESA de la aplicacion (el tenant).
 *
 * ESTA TABLA NO LLEVA ROW LEVEL SECURITY, y es a proposito.
 *
 * Se consulta en el selector de ingreso, antes de que exista sesion y por lo
 * tanto antes de que exista `app.tenant_id`. Si llevara RLS, el listado de
 * empresas para iniciar sesion devolveria cero filas siempre.
 *
 * Como compensacion, no se expone la tabla cruda: la pantalla de entrada lee
 * la vista `vw_opticas_publicas`, que deja pasar solo `id`, `slug` y `nombre`.
 * Ver docs/03.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opticas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();

            $tabla->string('nombre', 160);

            // Identificador corto y legible, para la URL y para el soporte.
            $tabla->string('slug', 80)->unique();

            /*
            | Correlacion con la que el equipo de Authentik declara la empresa.
            |
            | El equipo en Authentik se llama `<prefijo>:<correlacion>`, por
            | ejemplo `OpticaHefesto:centro`. Al entrar, ese sufijo se busca
            | contra esta columna y decide en que optica se entra. Sin ella no
            | hay ingreso. Ver docs/04.
            */
            $tabla->string('correlacion_identidad', 100)->unique();

            /*
            | Zona horaria. Se guarda por optica y no es global porque una
            | tienda con locales en otro huso deberia registrar su fecha de
            | entrega en hora local. En Chile el valor de uso es
            | `America/Santiago`.
            */
            $tabla->string('zona_horaria', 64)->default('America/Santiago');

            $tabla->boolean('activo')->default(true);

            $tabla->timestampsTz();

            $tabla->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opticas');
    }
};