<?php

declare(strict_types=1);

use App\Enums\ResultadoControlCalidad;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de controles de calidad al recibir un pedido del laboratorio.
 *
 * ESTA TABLA ES LA QUE EVITA EL RECLAMO
 * --------------------------------------
 * Si el laboratorio manda la graduacion equivocada, el momento de enterarse es
 * ANTES de entregarle los lentes al cliente. Despues no hay reclamo que
 * solver: el cliente ya se los puso.
 *
 * UN CONTROL POR PEDIDO
 * ---------------------
 * Hay indice unico en `pedido_laboratorio_id`. Si un control da `no_conforme`,
 * la correccion es un pedido NUEVO, no un segundo control sobre el mismo
 * pedido: el laboratorio mecaniza de nuevo y vuelve a entrar por el mismo
 * camino. Asi queda un solo veredicto por pedido y no hay que decidir cual de
 * los dos vale.
 *
 * `diferencias` es la lista de valores que no coinciden entre lo pedido y lo
 * recibido, calculada por App\Services\Calidad\ComparadorDeGraduaciones.
 *
 * SOBRE EL VEREDICTO AUTOMATICO
 * ----------------------------
 * El resultado lo pone una persona. El sistema compara valor por valor y
 * muestra las diferencias, pero la tolerancia con que se acepta una diferencia
 * (`config('optica.control_calidad.tolerancia_dioptrias')`) NACE EN NULL y no
 * se puede inventar: es una decision clinica del optometrista. Con la
 * tolerancia vacia, el veredicto queda siempre en manos de la persona. Ver
 * docs/06.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controles_calidad', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();

            $tabla->uuid('tenant_id');

            $tabla->foreign('tenant_id')->references('id')->on('opticas')->cascadeOnDelete();

            $tabla->uuid('pedido_laboratorio_id');

            /*
            | Un solo control por pedido. `cascadeOnDelete` porque el control
            | no vale nada sin el pedido que revisa.
            */
            $tabla->foreign('pedido_laboratorio_id')
                ->references('id')
                ->on('pedidos_laboratorio')
                ->cascadeOnDelete();

            $tabla->unique('pedido_laboratorio_id');

            /*
            | Quien hizo la revision.
            |
            | `restrictOnDelete`: el control de calidad es el registro de que
            | una persona concreto miro lo que llego del laboratorio. Si se
            | borra su cuenta, ese registro tiene que seguir diciendo quien
            | fue. Lo que se hace es desactivar la cuenta.
            */
            $tabla->uuid('revisado_por_usuario_id');

            $tabla->foreign('revisado_por_usuario_id')
                ->references('id')
                ->on('usuarios')
                ->restrictOnDelete();

            $tabla->string('resultado', 24)->default(ResultadoControlCalidad::Conforme->value);

            $tabla->date('fecha_revision');

            /*
            | Detalle de lo que no cuadraba.
            |
            | Lista en JSON, con una entrada por valor distinto:
            |   {"ojo": "derecho", "meridiano": "lejos", "campo": "esfera",
            |    "pedido": -2.25, "recibido": -2.75, "diferencia": -0.5}
            *
            | Se guarda aunque el resultado sea `conforme`: queda constancia de
            | que se reviso y de que se comparo todo y no encontro nada.
            */
            $tabla->jsonb('diferencias')->nullable();

            $tabla->text('observaciones')->nullable();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'resultado']);
            $tabla->index(['tenant_id', 'fecha_revision']);

            $tabla->check(
                "resultado in ('conforme', 'con_diferencias', 'no_conforme')",
                'controles_calidad_resultado_valido'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controles_calidad');
    }
};