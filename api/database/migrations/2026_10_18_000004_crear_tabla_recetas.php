<?php

declare(strict_types=1);

use App\Enums\EstadoReceta;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de recetas opticas.
 *
 * UNA RECETA ES UN DOCUMENTO CON VIDA PROPIA
 * ------------------------------------------
 * No es una medida de la persona: es un documento que se emite en una fecha,
 * que tiene un plazo de vigencia y que sirve para despachar lentes. Por eso
 * tiene numero, fecha de emision y estado, y por eso se archiva en vez de
 * actualizarse.
 *
 * LA GRADUACION NO ESTA ACA
 * -------------------------
 * Vive en `graduaciones`, una fila por ojo y por meridiano. Ver esa migracion
 * para el por que.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recetas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();

            $tabla->uuid('tenant_id');

            $tabla->foreign('tenant_id')->references('id')->on('opticas')->cascadeOnDelete();

            $tabla->uuid('paciente_id');

            $tabla->foreign('paciente_id')->references('id')->on('pacientes')->cascadeOnDelete();

            /*
            | Optometrista que emite la receta.
            |
            | `restrictOnDelete` y no `cascade`: si se borra la cuenta de un
            | optometrista, las recetas que emitio siguen existiendo y tienen
            | que seguir diciendo quien las emitio. Borrar al profesional
            | desactivaria su cuenta (columna `activo`), no su historia.
            */
            $tabla->uuid('optometrista_id');

            $tabla->foreign('optometrista_id')->references('id')->on('usuarios')->restrictOnDelete();

            /*
            | Numero de receta. Es el folio visible: el que va impreso, el que
            | cita el laboratorio y el que da el cliente si algo sale mal.
            | Unico dentro de la optica, no dentro de la base.
            */
            $tabla->string('numero', 40);

            $tabla->date('fecha_emision');

            /*
            | FECHA DE VENCIMIENTO: EL CAMPO MAS IMPORTANTE DE ESTA TABLA
            |
            | NACE EN NULL Y ESO ES UNA DECISION, NO UN OLVIDO.
            |
            | Una receta tiene plazo de vigencia y el plazo depende de la edad
            | del paciente. Ese plazo es NORMA SANITARIA CHILENA. En la maquina
            | donde se escribio esta aplicacion NO se pudo verificar contra la
            | norma vigente, asi que no se escribe ninguna fecha por omision.
            |
            | Que pasa mientras sea `null`:
            |   - el sistema NO vence recetas por su cuenta;
            |   - la interfaz avisa que el plazo esta pendiente de verificacion;
            |   - se puede emitir y despachar igual, dejando constancia en
            |     `motivo_sin_vencimiento` de por que se emitio sin plazo.
            |
            | Quien la carga: el optometrista, cuando la optica ya haya
            | verificado el plazo que le corresponde. Ver docs/06 y docs/07.
            |
            */
            $tabla->date('fecha_vencimiento')->nullable();

            /*
            | Por que se emite una receta sin fecha de vencimiento.
            |
            | Sin esto, una receta sin plazo seria indistinguible de una receta
            | a la que a alguien se le olvido la fecha. Con esto queda
            | dicho que se emitio a sabiendas. Es texto libre: el sistema no
            | decide que motivo es valido.
            */
            $tabla->text('motivo_sin_vencimiento')->nullable();

            /*
            | Estado de ciclo de vida. NO dice si la receta esta vigente: eso se
            | calcula con `fecha_vencimiento`. Este campo es para lo que la
            | persona decide. Ver App\Enums\EstadoReceta.
            */
            $tabla->string('estado', 24)->default(EstadoReceta::Emitida->value);

            /*
            | Distancia interpupilar en milimetros.
            |
            | Es la medida que separa las pupilas, y la necesita el laboratorio
            | para centrar los lentes en el armazon. Nullable porque hay
            | recetas que no la traen cargada.
            */
            $tabla->decimal('distancia_interpupilar_mm', 5, 2)->nullable();

            $tabla->text('observaciones')->nullable();

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'numero']);
            $tabla->index(['tenant_id', 'paciente_id']);
            $tabla->index(['tenant_id', 'estado']);
            $tabla->index(['tenant_id', 'fecha_vencimiento']);

            // Una receta no puede vencerse antes de emitirse. Sin esta
            // restriccion se podria emitir una receta ya vencida y pasaria
            // inadvertida hasta que alguien la imprimiera.
            $tabla->check(
                'fecha_vencimiento is null or fecha_vencimiento >= fecha_emision',
                'recetas_vencimiento_posterior_a_emision'
            );

            $tabla->check(
                "estado in ('emitida', 'anulada', 'reemplazada')",
                'recetas_estado_valido'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recetas');
    }
};