<?php

declare(strict_types=1);

use App\Enums\RolOptica;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de usuarios de la optica: las personas que operan el sistema.
 *
 * NO guarda contrasenas. OpticaHefesto usa OpenID Connect con Authentik y la
 * credencial vive alla. Ver App\Services\Auth. Ver docs/07.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();

            $tabla->uuid('tenant_id');

            $tabla->foreign('tenant_id')->references('id')->on('opticas')->cascadeOnDelete();

            $tabla->string('nombre', 160);

            /*
            | El correo NO es unico en toda la base: una misma persona puede
            | tener cuentas en dos opticas distintas, y cada cuenta pertenece a
            | su propia empresa. La unicidad es por `(tenant_id, correo)`.
            */
            $tabla->string('correo', 190);

            /*
            | Rol dentro de la optica. Es una cadena y no un enum de base de
            | datos a proposito: agregar un rol es cambiar el enum de PHP, no
            | una migracion. El significado de cada valor esta en
            | App\Enums\RolOptica.
            */
            $tabla->string('rol', 32)->default(RolOptica::Mostrador->value);

            $tabla->boolean('activo')->default(true);

            $tabla->timestampTz('ultimo_ingreso_en')->nullable();

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'correo']);
            $tabla->index('rol');
            $tabla->index('activo');

            // Restriccion de integridad: el rol tiene que ser uno de los tres
            // que el sistema conoce. Es el unico enum con CHECK en el estudio,
            // y aun asi conviene: un INSERT a mano con un rol inventado
            // dejaria a la persona sin permisos y sin que nadie lo note.
            $tabla->check(
                "rol in ('mostrador', 'optometrista', 'administrador')",
                'usuarios_rol_valido'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};