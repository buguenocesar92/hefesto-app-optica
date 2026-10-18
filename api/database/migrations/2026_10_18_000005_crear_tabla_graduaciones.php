<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de graduaciones: el dato optico de verdad.
 *
 * POR QUE UNA FILA POR OJO Y POR MERIDIANO
 * -----------------------------------------
 * Una receta completa son 4 filas: dos ojos por dos meridianos (lejos y cerca).
 * La alternativa tentadora es una sola fila con columnas `esfera_lejos`,
 * `esfera_cerca`, `eje_cerca` y asi. Se ve mas comoda y es una mala idea por
 * dos motivos:
 *
 *   1. Repite el nombre del meridiano en cada columna, y a la mitad de las
 *      columnas se les olvida el sufijo.
 *   2. El dia que aparezca un tercer meridiano hay que agregar tres columnas
 *      mas y otra migracion, sobre una tabla que ya tiene datos de pacientes.
 *
 * Con una fila por combinacion, el meridiano es un dato, no parte del nombre.
 * Agregar uno es agregar un valor a App\Enums\Meridiano. Ver docs/02.
 *
 * LA ESTRUCTURA LA IMPONE LA BASE, NO SOLO LA APLICACION
 * ----------------------------------------------------
 * Las restricciones CHECK de mas abajo hacen que una graduacion mal formada no
 * pueda existir, ni siquiera con un INSERT escrito a mano o con un error en la
 * aplicacion. Es la defensa contra el peor error posible en una optica: despachar
 * lentes con una graduacion que no tiene sentido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduaciones', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();

            $tabla->uuid('tenant_id');

            $tabla->foreign('tenant_id')->references('id')->on('opticas')->cascadeOnDelete();

            $tabla->uuid('receta_id');

            $tabla->foreign('receta_id')->references('id')->on('recetas')->cascadeOnDelete();

            /*
            | Ojo al que pertenece la fila.
            |
            | Se guarda el texto (`derecho` / `izquierdo`) y no `0` / `1`: una
            | receta se lee, se imprime y se manda al laboratorio, y en esos
            | tres momentos el numero obliga a recordar cual era cual.
            */
            $tabla->string('ojo', 16);

            /*
            | Meridiano: `lejos` o `cerca`. Ver la nota de cabecera.
            */
            $tabla->string('meridiano', 16);

            /*
            | LA GRADUACION, UN VALOR POR CAMPO.
            |
            | `esfera`: el equivalente de dioptrias de la esfera del ojo. Con
            | signo: mas para hipermropia, menos para miopia. El sistema NO
            | decide la convencion de signo del cilindro: se guarda la que
            | use la optica y la valida el FormRequest.
            |
            | `cilindro`: la correccion del astigmatismo. Cero cuando no hay.
            |
            | `eje`: el angulo al que esta orientado el astigmatismo, en
            | grados, de 0 a 180. Cuando `cilindro` es cero, el eje vale cero y
            | no significa nada. La restriccion de mas abajo lo garantiza.
            |
            | `adiccion`: lo que se suma para ver de cerca. Solo tiene sentido
            | en el meridiano de cerca, y la restriccion lo prohibe en lejos.
            |
            */
            $tabla->decimal('esfera', 5, 2);
            $tabla->decimal('cilindro', 5, 2);
            $tabla->smallInteger('eje');
            $tabla->decimal('adiccion', 5, 2)->nullable();

            $tabla->timestampsTz();

            /*
            | Una sola fila por ojo y meridiano dentro de una receta.
            |
            | Sin este indice, un doble envio crearia dos filas de lejos para el
            | mismo ojo con valores distintos, y no habria forma de saber
            | cual de las dos se leyo en el mostrador.
            */
            $tabla->unique(['receta_id', 'ojo', 'meridiano']);

            $tabla->index(['tenant_id', 'receta_id']);

            // El eje es un angulo: va de 0 a 180 grados y nunca mas.
            $tabla->check('eje >= 0 and eje <= 180', 'graduaciones_eje_en_rango');

            // Sin astigmatismo no hay eje. Si el cilindro es cero, el eje
            // tiene que ser cero tambien.
            $tabla->check('cilindro <> 0 or eje = 0', 'graduaciones_eje_sin_cilindro');

            // La adicion se resta para ver de cerca, nunca se suma: si fuera
            // negativa, la persona veria peor de cerca que de lejos, que es
            // justo lo contrario de lo que se quiere lograr.
            $tabla->check(
                'adiccion is null or adiccion > 0',
                'graduaciones_adicion_positiva'
            );

            /*
            | La adicion pertenece al meridiano de cerca.
            |
            | Si se admitiera en la fila de lejos, el mismo numero podria
            | significar dos cosas distintas segun de donde se lea. Esto obliga
            | a que la estructura sea inequivoca.
            */
            $tabla->check(
                "(meridiano = 'cerca' and adiccion is not null) "
                    . "or (meridiano = 'lejos' and adiccion is null)",
                'graduaciones_adicion_solo_en_cerca'
            );

            $tabla->check(
                "ojo in ('derecho', 'izquierdo')",
                'graduaciones_ojo_valido'
            );

            $tabla->check(
                "meridiano in ('lejos', 'cerca')",
                'graduaciones_meridiano_valido'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduaciones');
    }
};