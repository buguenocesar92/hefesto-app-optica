<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Alta de un pedido a laboratorio.
 *
 * `fecha_prometida` ES NULLABLE, Y NO ES UNA CARENCIA
 * --------------------------------------------------
 * El plazo prometido es COMERCIAL: lo negocia la optica con su laboratorio y
 * cambia de temporada a temporada. No se puede verificar ningun valor, asi que
 * el sistema no propone ninguno por omision. Se puede crear el pedido sin
 * fecha prometida; lo que no se puede es cargar una fecha anterior al envio.
 *
 * `compatibilidad` NO SE ENVIA DESDE EL NAVEGADOR
 * -----------------------------------------------
 * El veredicto lo calcula el servidor con
 * App\Services\Optica\VerificadorDeCompatibilidad, con los datos del armazon y
 * del lente que estan en la base. Si el navegador mandara su propio veredicto,
 * un formulario alterado podria forzar un "compatible" que nadie comprobo.
 */
class GuardarPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\PedidoLaboratorio::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:40'],
            'paciente_id' => ['required', 'uuid'],
            'receta_id' => ['nullable', 'uuid'],
            'armazon_id' => ['nullable', 'uuid'],
            'lente_id' => ['nullable', 'uuid'],
            'laboratorio' => ['nullable', 'string', 'max:160'],
            'fecha_envio' => ['nullable', 'date'],
            'fecha_prometida' => ['nullable', 'date', 'after_or_equal:fecha_envio'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador): void {
            /*
            | No se promete una fecha anterior a la de envio. Es un descuido
            | tipico y dejaria el pedido marcado como atrasado desde el
            | primeiro dia.
            */
            $envio = $this->input('fecha_envio');
            $promesa = $this->input('fecha_prometida');

            if (is_string($envio) && is_string($promesa) && $envio !== '' && $promesa !== '') {
                if (strtotime($promesa) < strtotime($envio)) {
                    $validador->errors()->add(
                        'fecha_prometida',
                        'No se puede prometer una fecha anterior a la de envio.'
                    );
                }
            }
        });
    }

    /**
     * De donde salio la fecha prometida, para dejarlo escrito.
     *
     * Queda guardado en `origen_fecha_prometida` para que nadie asuma que el
     * sistema la invento si en realidad la puso una persona.
     */
    public function origen(): string
    {
        $promesa = $this->input('fecha_prometida');

        return is_string($promesa) && $promesa !== '' ? 'manual' : 'pendiente';
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_prometida.after_or_equal' => 'No se puede prometer una fecha anterior a la de envio.',
        ];
    }
}