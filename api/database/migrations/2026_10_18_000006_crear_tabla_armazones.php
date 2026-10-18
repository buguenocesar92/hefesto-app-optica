<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de armazones del catalogo.
 *
 * UN ARMazon NO ES INVENTARIO GENERICO
 * -----------------------------------
 * De un armazon interesa una cosa mas que de cualquier otro producto: sus
 * MEDIDAS DE CAJA. Son las tres medidas que estan grabadas o impresas en el
 * patilla del armazon y que dicen el tamano de la lente que entra:
 *
 *     ancho_varilla_mm: el ancho de un lente, de borde a borde (la "A")
 *     puente_mm:        el espacio entre los dos lentes (el "DBL")
 *     alto_mm:          el alto de la caja, de arriba a abajo (la "B")
 *
 * Con las dos primeras se arma el ancho total de la caja, que es lo que tiene
 * que caber dentro de la pastilla circular del lente. La tercera importa
 * porque la caja no es cuadrada: un armazon bajo y ancho y uno alto y angosto
 * pueden pedir pastillas distintas.
 *
 * NO SE PONEN VALORES DE EJEMPLO: las medidas se cargan del armazon real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('armazones', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();

            $tabla->uuid('tenant_id');

            $tabla->foreign('tenant_id')->references('id')->on('opticas')->cascadeOnDelete();

            /*
            | Codigo del armazon en el catalogo de la optica. Unico dentro de
            | la empresa, no dentro de la base.
            */
            $tabla->string('codigo', 60);

            $tabla->string('nombre', 160);
            $tabla->string('marca', 120)->nullable();
            $tabla->string('modelo', 120)->nullable();

            /*
            | Material del armazon.
            |
            | Es texto y no un enum: cada fabricante usa su propia denominacion
            | (acetato, titanio, Memory, metal semirrigido) y una lista cerrada
            | obligaria a tocar el enum cada vez que llega un modelo nuevo.
            */
            $tabla->string('material', 80)->nullable();

            $tabla->string('color', 80)->nullable();

            /*
            | LAS MEDIDAS DE CAJA. Todas obligatorias y todas en milimetros.
            |
            | Sin estas tres medidas el armazon no sirve para el calculo de
            | compatibilidad, asi que son NOT NULL: es preferible no cargar el
            | armazon antes que cargarlo sin medidas.
            */
            $tabla->decimal('ancho_varilla_mm', 5, 2);
            $tabla->decimal('puente_mm', 5, 2);
            $tabla->decimal('alto_mm', 5, 2);

            $tabla->boolean('activo')->default(true);

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'codigo']);
            $tabla->index(['tenant_id', 'nombre']);
            $tabla->index('activo');

            // Las tres medidas tienen que ser positivas. Un armazon con una
            // medida en cero o negativa es un error de tipeo, y dejaria pasar
            // un ancho de caja sin sentido.
            $tabla->check('ancho_varilla_mm > 0', 'armazones_ancho_positivo');
            $tabla->check('puente_mm > 0', 'armazones_puente_positivo');
            $tabla->check('alto_mm > 0', 'armazones_alto_positivo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('armazones');
    }
};