<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de lentes del catalogo.
 *
 * UN LENTE NO ES INVENTARIO GENERICO
 * ----------------------------------
 * De un lente interesan cuatro cosas, y ninguna es el precio:
 *
 *   - `material`: de que esta hecho (cristal, resina, policarbonato...)
 *   - `indice_refraccion`: que tan denso es. Un indice mas alto afina el
 *     lente y lo hace mas plano y mas facil de usar, pero cambia el color y
 *     el peso. Ver la nota de abajo.
 *   - `tratamientos`: queued que lleva encima (antirreflejo, endurecido,
 *     fotocromico...)
 *   - `diametro_corte_mm`: DE QUE DIAMETRO SE PUEDE MECANIZAR.
 *
 * LA ULTIMA ES LA QUE SOSTIENE ESTE PRODUCTO
 * ------------------------------------------
 * El lente se fabrica sobre una pastilla circular y de ahi se mecaniza. Si el
 * diametro de la pastilla no alcanza para la caja del armazon mas el borde,
 * la hecha no sirve. Este valor es el que se compara en
 * App\Services\Optica\VerificadorDeCompatibilidad. Ver docs/06.
 *
 * SOBRE EL INDICE DE REFRACCION Y LOS PRECIOS
 * -------------------------------------------
 * `indice_refraccion` es nullable y el catalogo no trae precios. Los valores
 * de indice que ofrece cada fabricante cambian segun el modelo, el acabado y
 * el mercado, asi que no se pueden escribir aca: se cargan desde el catalogo
 * real de la optica. Ver docs/02 y docs/10.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lentes', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();

            $tabla->uuid('tenant_id');

            $tabla->foreign('tenant_id')->references('id')->on('opticas')->cascadeOnDelete();

            $tabla->string('codigo', 60);
            $tabla->string('nombre', 160);
            $tabla->string('marca', 120)->nullable();

            /*
            | Material del lente. Texto y no enum: cada fabricante nombra los
            | materiales como quiere.
            */
            $tabla->string('material', 80)->nullable();

            /*
            | Indice de refraccion del material.
            |
            | Nullable a proposito: el catalogo no lo trae escrito. La optica
            | lo carga desde la ficha del fabricante. Un lente con indice en
            | NULL no impide cargar el lente ni crear un pedido; lo que hace es
            | que la interfaz lo marque como incompleto. Ver docs/10.
            */
            $tabla->decimal('indice_refraccion', 3, 2)->nullable();

            /*
            | Tratamientos del lente.
            |
            | Es JSON porque la lista depende del fabricante y cambia seguido:
            | "antirreflejo", "endurecido", "fotocromico", "filtro UV" y
            | combinaciones de ellos. Una tabla aparte obligaria a migrar cada
            | vez que aparece un tratamiento nuevo.
            */
            $tabla->jsonb('tratamientos')->nullable();

            /*
            | DIAMETRO DE MECANIZADO, en milimetros.
            |
            | Nullable a proposito: depende del diametro de pastilla que tenga
            | disponible el laboratorio para ese material e indice. Mientras
            | sea NULL, la verificacion de compatibilidad devuelve
            | `no_verificable`: el sistema no puede afirmar que el lente sirve
            | si no sabe de que diametro se corta.
            */
            $tabla->decimal('diametro_corte_mm', 5, 2)->nullable();

            /*
            | Un lente progresivo necesita mas altura de caja que uno simple,
            | porque tiene que alojar la zona de cerca. Sirve para avisar en la
            | interfaz cuando se elige un armazon de poco alto.
            */
            $tabla->boolean('es_progresivo')->default(false);

            $tabla->boolean('activo')->default(true);

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'codigo']);
            $tabla->index(['tenant_id', 'nombre']);
            $tabla->index('activo');

            // Si el indice se carga, tiene que ser positivo: un indice de
            // refraccion en cero o negativo no corresponde a ningun material
            // que se venda.
            $tabla->check(
                'indice_refraccion is null or indice_refraccion > 0',
                'lentes_indice_positivo'
            );

            $tabla->check(
                'diametro_corte_mm is null or diametro_corte_mm > 0',
                'lentes_diametro_positivo'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lentes');
    }
};