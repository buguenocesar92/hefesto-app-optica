<?php

declare(strict_types=1);

use App\Enums\EstadoAjuste;
use App\Enums\EstadoEntrega;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de entregas de lentes al cliente.
 *
 * ENTREGAR NO ES EL FINAL
 * ----------------------
 * Un lente recien entregado casi nunca esta bien ajustado la primera vez: el
 * armazon hay que sentarlo en la nariz, la altura hay que bajarla un poco, la
 * patilla hay que abrirla. Por eso la entrega tiene estado y por eso existe
 * `ajustes` al lado.
 *
 * La fecha prometida del pedido y la fecha de entrega de aqui son cosas
 * distintas: la primera es lo prometido por el laboratorio, la segunda es lo
 * que la optica le entrega a su cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();

            $tabla->uuid('tenant_id');

            $tabla->foreign('tenant_id')->references('id')->on('opticas')->cascadeOnDelete();

            /*
            | Pedido que se entrega. Unico: un pedido se entrega una vez. Si el
            | cliente devuelve los lentes, se cambia el estado de ESTA entrega,
            | no se crea otra.
            */
            $tabla->uuid('pedido_laboratorio_id');

            $tabla->foreign('pedido_laboratorio_id')
                ->references('id')
                ->on('pedidos_laboratorio')
                ->cascadeOnDelete();

            $tabla->unique('pedido_laboratorio_id');

            $tabla->uuid('paciente_id');

            $tabla->foreign('paciente_id')->references('id')->on('pacientes')->cascadeOnDelete();

            $tabla->string('estado', 24)->default(EstadoEntrega::Registrada->value);

            $tabla->date('fecha_registro');
            $tabla->date('fecha_entrega')->nullable();

            /*
            | Quien recibio los lentes. Texto y no id a `usuarios`: muchas
            | opticas entregan a un familiar, a un represente o a un retiro por
            | parte de tercero, y ninguno de ellos tiene cuenta en el sistema.
            | El nombre basta para saber a quien se le entrego.
            */
            $tabla->string('recibido_por', 160)->nullable();

            $tabla->text('observaciones')->nullable();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'estado']);
            $tabla->index(['tenant_id', 'paciente_id']);

            $tabla->check(
                "estado in ('registrada', 'entregada', 'devuelta')",
                'entregas_estado_valido'
            );

            /*
            | Si la entrega esta hecha, tiene que haber fecha de entrega; si
            | todavia no se ha hecho, no puede haber fecha. Sin esta
            | restriccion se puede marcar como entregada sin fecha, y la orden
            | de la cola de ajustes deja de tener sentido.
            */
            $tabla->check(
                "(estado = 'entregada' and fecha_entrega is not null) "
                    . "or (estado <> 'entregada')",
                'entregas_fecha_para_entregadas'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas');
    }
};