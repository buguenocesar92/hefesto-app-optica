<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de bitacora de accesos a recetas y graduaciones.
 *
 * POR QUE ESTA TABLA EXISTE
 * -------------------------
 * Una graduacion optica es informacion de salud. Leyda, dice como ve el
 * paciente. Por eso no basta con protegerla: hay que poder responder, meses
 * despues, quien la abrio y cuando.
 *
 * Se registra TANTO LO QUE SE PUDO ABRIR COMO LO QUE NO. Un intento denegado
 * es tan informativo como uno permitido: es la senal de que alguien esta
 * probando el sistema con el rol equivocado.
 *
 * POR QUE `usuario_id` ES NULLABLE
 * -------------------------------
 * El intento denegado mas importante es el de quien NO tiene cuenta, o la
 * tiene desactivada. Si la columna fuera obligatoria, ese intento no se podria
 * registrar y el hueco seria justo el que interesa.
 *
 * LA BITACORA SE ESCRIBE EN OTRA CONEXION
 * ---------------------------------------
 * Ver App\Services\Recetas\BitacoraRecetas. Una peticion denegada termina con
 * la transaccion revertida, y el registro tiene que sobrevivir a esa reversa.
 *
 * SOBRE LA CONSERVACION
 * ---------------------
 * `config('optica.retencion.accesos_recetas_dias')` NACE EN NULL: no se pudo
 * verificar que plazo corresponde. Con la variable vacia, la aplicacion no
 * purga nada. Ver docs/07.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accesos_recetas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();

            $tabla->uuid('tenant_id');

            $tabla->foreign('tenant_id')->references('id')->on('opticas')->cascadeOnDelete();

            $tabla->uuid('usuario_id')->nullable();

            $tabla->foreign('usuario_id')->references('id')->on('usuarios')->nullOnDelete();

            /*
            | Que se intento ver: `receta` o `graduacion`. Guardar el tipo
            | separado del id permite decir "accedio a la receta tal" sin
            | depender de la tabla donde este el recurso.
            */
            $tabla->string('recurso', 32);
            $tabla->uuid('recurso_id')->nullable();

            /*
            | Que se intento hacer: `ver`, `crear`, `actualizar`, `eliminar`.
            */
            $tabla->string('accion', 24);

            $tabla->boolean('permitido')->default(true);

            /*
            | Por que se denego, cuando se denego. Texto corto y legible:
            | "rol sin acceso a graduaciones", "receta de otra optica".
            */
            $tabla->string('motivo', 160)->nullable();

            $tabla->string('ip', 45)->nullable();
            $tabla->string('agente', 255)->nullable();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'ocurrido_en']);
            $tabla->index(['tenant_id', 'usuario_id']);
            $tabla->index(['tenant_id', 'recurso', 'recurso_id']);

            // Un registro de acceso sin fecha no sirve para nada: la pregunta
            // "cuando" es la primera que se hace.
            $tabla->index('ocurrido_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos_recetas');
    }
};