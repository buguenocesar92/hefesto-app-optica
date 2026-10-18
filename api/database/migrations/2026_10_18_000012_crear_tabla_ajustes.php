<?php

declare(strict_types=1);

use App\Enums\EstadoAjuste;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de ajustes posteriores a la entrega.
 *
 * LO QUE SOSTIENE LA RELACION CON EL CLIENTE
 * -------------------------------------------
 * Todo lente se ajusta despues de entregado. Un armazon que aprieta, que se
 * desliza o que le queda alto no es un defecto de fabricacion: es una entrega
 * que todavia no termino.
 *
 * El motivo del ajuste es el dato mas util de esta tabla, mas que el resultado:
 * saber que se ajustan mal las patillas, o que el armazon elegido quedo chico
 * para la cara del cliente, es informacion que sirve para el catalogo y para
 * el siguiente pedido de esa persona.
 *
 * SOBRE LOS DIAS DE GARANTIA
 * -------------------------
 * Esta tabla NO tiene una columna de dias de garantia, y es a proposito. Los
 * dias de garantia sugeridos estan en
 * `config('optica.entrega.dias_de_garantia_sugeridos')` y NACEN EN NULL porque
 * son una decision comercial de la optica y no un plazo legal que se pueda
 * verificar aca. El ajuste se puede registrar en cualquier momento. Ver
 * docs/07.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();

            $tabla->uuid('tenant_id');

            $tabla->foreign('tenant_id')->references('id')->on('opticas')->cascadeOnDelete();

            /*
            | Entrega a la que se le ajusta el armazon.
            |
            | `cascadeOnDelete`: si se borra la entrega, sus ajustes no
            | significan nada. Solo una entrega YA HECHA admite ajustes, y eso
            | lo comprueba el FormRequest, no la base: aqui solo se garantiza
            | la integridad de la referencia.
            */
            $tabla->uuid('entrega_id');

            $tabla->foreign('entrega_id')->references('id')->on('entregas')->cascadeOnDelete();

            $tabla->date('fecha');

            /*
            | Que se ajusto. Texto y no enum: la lista de motivos posibles la
            | escribe la optica segun su forma de trabajar, y una lista cerrada
            | obligaria a agregar migraciones cada vez que aparece uno nuevo.
            | Los que la interfaz sugiere estan en el FormRequest.
            */
            $tabla->string('motivo', 120);

            $tabla->text('descripcion')->nullable();

            $tabla->string('estado', 24)->default(EstadoAjuste::Pendiente->value);

            /*
            | Si hubo que cambiar el lente. Un ajuste de patilla no cambia el
            | lente; un lente que no se ve bien, si. Distinguirlo sirve para
            | saber cuando el problema fue del armazon y cuando del lente.
            */
            $tabla->boolean('lente_sustituido')->default(false);

            $tabla->text('observaciones')->nullable();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'fecha']);
            $tabla->index(['tenant_id', 'entrega_id']);
            $tabla->index(['tenant_id', 'estado']);

            $tabla->check(
                "estado in ('pendiente', 'realizado', 'no_requerido')",
                'ajustes_estado_valido'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
    }
};