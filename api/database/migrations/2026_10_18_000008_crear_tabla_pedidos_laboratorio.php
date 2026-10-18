<?php

declare(strict_types=1);

use App\Enums\EstadoPedido;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de pedidos a laboratorio.
 *
 * LA OPTICA NO FABRICA: ENCARGA
 * -----------------------------
 * Por eso este pedido tiene estados que son del mundo externo (lo que el
 * laboratorio confirmo) y no solo estados internos, y por eso tiene una FECHA
 * PROMETIDA.
 *
 * SOBRE LA FECHA PROMETIDA
 * -----------------------
 * `fecha_prometida` NACE EN NULL Y ESO ES UNA DECISION, NO UN OLVIDO.
 *
 * El plazo de un laboratorio es COMERCIAL: cambia de laboratorio a laboratorio,
 * de material a material y de temporada a temporada. No se pudo verificar
 * ningun valor en esta maquina, asi que no se escribe ninguno. Lo carga la
 * optica, por pedido, o mediante
 * `config('optica.laboratorio.dias_promesa_por_omision')`, que tambien nace
 * en NULL. Ver docs/06.
 *
 * `origen_fecha_prometida` deja escrito de donde salio la fecha, para que nadie
 * asuma que el sistema la invento si en realidad la puso una persona.
 *
 * SOBRE EL DESGLOSE DE COMPATIBILIDAD
 * ----------------------------------
 * `compatibilidad_resultado` y `compatibilidad_desglose` guardan el veredicto
 * del servicio de compatibilidad en el momento en que se creo el pedido, con el
 * desglose completo del calculo. Se guardan aunque el resultado sea
 * `no_verificable`: el calculo geometrico que si se pudo hacer queda
 * registrado. Ver docs/06.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_laboratorio', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();

            $tabla->uuid('tenant_id');

            $tabla->foreign('tenant_id')->references('id')->on('opticas')->cascadeOnDelete();

            $tabla->string('codigo', 40);

            $tabla->uuid('paciente_id');

            $tabla->foreign('paciente_id')->references('id')->on('pacientes')->cascadeOnDelete();

            /*
            | Receta que origina el pedido.
            |
            | Nullable y con `nullOnDelete` porque un pedido ya enviado al
            | laboratorio no puede quedar huerfano: si la receta se borra, el
            | pedido se conserva como documento comercial. Ademas, si se
            | colgara en cascada, borrarla llevaria el pedido y su historial.
            */
            $tabla->uuid('receta_id')->nullable();

            $tabla->foreign('receta_id')->references('id')->on('recetas')->nullOnDelete();

            /*
            | Armazon y lente elegidos.
            |
            | Nullable porque un pedido se puede crear sin haber cerrado la
            | eleccion. `restrictOnDelete` en el armazon: si el armazon ya se
            | pidio a otro laboratorio, borrarlo del catalogo dejaria un pedido
            | en el aire. Lo que se hace es desactivar el armazon.
            */
            $tabla->uuid('armazon_id')->nullable();

            $tabla->foreign('armazon_id')->references('id')->on('armazones')->restrictOnDelete();

            $tabla->uuid('lente_id')->nullable();

            $tabla->foreign('lente_id')->references('id')->on('lentes')->restrictOnDelete();

            $tabla->string('laboratorio', 160)->nullable();

            $tabla->string('estado', 24)->default(EstadoPedido::Borrador->value);

            $tabla->date('fecha_envio')->nullable();
            $tabla->date('fecha_prometida')->nullable();
            $tabla->date('fecha_recepcion')->nullable();

            /*
            | De donde salio la fecha prometida: `configuracion`, `manual` o
            | `pendiente` si no hay ninguna. Ver la nota de cabecera.
            */
            $tabla->string('origen_fecha_prometida', 24)->default('pendiente');

            /*
            | Veredicto de compatibilidad armazon/lente al crear el pedido, con
            | el desglose del calculo. `no_verificable` es un valor valido y
            | frecuente mientras el catalogo no este completo.
            */
            $tabla->string('compatibilidad_resultado', 32)->nullable();
            $tabla->jsonb('compatibilidad_desglose')->nullable();

            $tabla->text('notas')->nullable();

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'codigo']);
            $tabla->index(['tenant_id', 'estado']);
            $tabla->index(['tenant_id', 'fecha_prometida']);
            $tabla->index(['tenant_id', 'paciente_id']);

            // No se puede prometer algo para antes de mandarlo.
            $tabla->check(
                'fecha_prometida is null or fecha_envio is null or fecha_prometida >= fecha_envio',
                'pedidos_promesa_posterior_a_envio'
            );

            $tabla->check(
                "estado in ('borrador', 'enviado', 'aceptado', 'en_produccion', 'recibido', 'cancelado')",
                'pedidos_estado_valido'
            );

            $tabla->check(
                "compatibilidad_resultado is null or compatibilidad_resultado in ('compatible', 'incompatible', 'no_verificable')",
                'pedidos_compatibilidad_valida'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_laboratorio');
    }
};