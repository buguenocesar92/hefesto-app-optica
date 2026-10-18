<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de pacientes de la optica.
 *
 * El paciente es una persona de la que se guarda lo minimo para atenderla y
 * cobrarle. NO guarda graduaciones: esas viven en `recetas` y `graduaciones`,
 * para que borrar una ficha de un paciente no arrastre su historia optica y
 * para que el acceso a la graduacion se pueda auditar por separado. Ver
 * docs/02.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();

            $tabla->uuid('tenant_id');

            $tabla->foreign('tenant_id')->references('id')->on('opticas')->cascadeOnDelete();

            /*
            | RUT chileno, como texto y no como numero.
            |
            | Como numero se pierden los ceros a la izquierda y el digito
            | verificador, y un RUT mal cargado no se puede usar para facturar.
            | El formato guardado es el habitual con guion: `12.345.678-9`.
            | Es nullable porque no toda persona que compra lentes trae RUT.
            */
            $tabla->string('rut', 16)->nullable();

            $tabla->string('nombres', 120);
            $tabla->string('apellidos', 120);

            /*
            | Fecha de nacimiento.
            |
            | Es lo que decide la franja de vigencia de la receta. Por eso es
            | nullable y no obligatoria: si falta, el calculo de vencimiento
            | devuelve "sin plazo" y la interfaz avisa, en vez de adivinar una
            | edad. Ver App\Services\Recetas\CalculadoraDeVencimiento.
            */
            $tabla->date('fecha_nacimiento')->nullable();

            $tabla->string('telefono', 32)->nullable();
            $tabla->string('correo', 190)->nullable();

            $tabla->boolean('activo')->default(true);

            $tabla->timestampsTz();

            // Un RUT no puede repetirse dentro de la misma optica. En
            // PostgreSQL un indice unico admite varios NULL, que es justo lo
            // que se quiere: varios pacientes sin RUT pueden convivir.
            $tabla->unique(['tenant_id', 'rut']);
            $tabla->index(['tenant_id', 'apellidos', 'nombres']);
            $tabla->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};