<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de sesiones.
 *
 * NO LLEVA ROW LEVEL SECURITY Y NO LLEVA `tenant_id`.
 *
 * La sesion se crea ANTES de saber en que optica se entra: el ingreso empieza
 * en Authentik, sin sesion de la aplicacion. Si esta tabla tuviera RLS, la
 * escritura de la sesion fallaria en el primer ingreso de la historia.
 *
 * El contenido de la sesion tampoco tiene datos de salud: guarda el id de
 * usuario, el nombre de la conexion y un csrf. Nada de graduaciones. Ver
 * docs/03.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $tabla): void {
            $tabla->string('id')->primary();

            /*
            | La optica se guarda como texto y no como id: es informacion de
            | apoyo para el registro, no una llave de seguridad. Quien manda
            | sobre las filas de una optica es la RLS de las tablas de datos,
            | no esta columna.
            */
            $tabla->uuid('optica_id')->nullable();
            $tabla->foreign('optica_id')->references('id')->on('opticas')->cascadeOnDelete();

            $tabla->uuid('usuario_id')->nullable();

            $tabla->string('ip_address', 45)->nullable();
            $tabla->text('user_agent')->nullable();
            $tabla->longText('payload');
            $tabla->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};