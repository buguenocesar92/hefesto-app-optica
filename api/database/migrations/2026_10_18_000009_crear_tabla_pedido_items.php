<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de items del pedido: un renglon por ojo.
 *
 * POR QUE SE COPIA LA GRADUACION Y NO SE REFERENCIA
 * -------------------------------------------------
 * `graduacion_pedida` guarda una COPIA de la graduacion tal como se mando al
 * laboratorio, en JSON, y no un id a `graduaciones`.
 *
 * La razon es que un pedido es un documento. Si se guardara solo el
 * `receta_id`, bastaria con que alguien corrigiera la receta despues de
 * enviado el pedido para que el sistema mostrara una graduacion que nunca se
 * fabrico, y el control de calidad compararia lo recibido contra algo que no
 * es lo que se pidio. Copiando, la comparacion es contra lo que realmente
 * salio de la optica.
 *
 * La copia se arma al crear el pedido, desde los datos de `graduaciones`. La
 * graduacion canonica sigue viviendo en `graduaciones`; aqui solo queda la foto
 * de lo que se pidio. Ver docs/02.
 *
 * `graduacion_recibida` se llena en la recepcion, y es lo que permite
 * detectar que el laboratorio mando otra cosa antes de entregarle al
 * cliente. Ver docs/02.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_items', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();

            $tabla->uuid('tenant_id');

            $tabla->foreign('tenant_id')->references('id')->on('opticas')->cascadeOnDelete();

            $tabla->uuid('pedido_laboratorio_id');

            $tabla->foreign('pedido_laboratorio_id')
                ->references('id')
                ->on('pedidos_laboratorio')
                ->cascadeOnDelete();

            /*
            | Ojo del renglon. Un item por ojo: casi siempre el mismo lente en
            | los dos, pero no siempre (una adicion distinta por ojo, o un
            | progresivo con correccion distinta), y el laboratorio mecaniza
            | cada lado por separado.
            */
            $tabla->string('ojo', 16);

            /*
            | Lente concreto de este ojo. Puede diferir del `lente_id` del
            | pedido cuando los dos ojos llevan lentes distintos.
            */
            $tabla->uuid('lente_id')->nullable();

            $tabla->foreign('lente_id')->references('id')->on('lentes')->nullOnDelete();

            $tabla->smallInteger('cantidad')->default(1);

            /*
            | COPIA de la graduacion enviada al laboratorio, por meridiano.
            |
            | Forma del JSON:
            |   {"lejos": {"esfera": .., "cilindro": .., "eje": ..},
            |    "cerca": {"esfera": .., "cilindro": .., "eje": ..,
            |              "adiccion": ..}}
            */
            $tabla->jsonb('graduacion_pedida')->nullable();

            /*
            | COPIA de la graduacion que llego de vuelta. Se llena en la
            | recepcion y es la base del control de calidad.
            */
            $tabla->jsonb('graduacion_recibida')->nullable();

            $tabla->timestampsTz();

            // Un renglon por ojo y por pedido. Sin esto, dos insertions con el
            // mismo ojo crearian dos renglones y el control de calidad
            // compararia contra el equivocado.
            $tabla->unique(['pedido_laboratorio_id', 'ojo']);
            $tabla->index(['tenant_id', 'pedido_laboratorio_id']);

            $tabla->check("ojo in ('derecho', 'izquierdo')", 'pedido_items_ojo_valido');
            $tabla->check('cantidad > 0', 'pedido_items_cantidad_positiva');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_items');
    }
};