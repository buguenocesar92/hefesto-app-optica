<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Registro de una entrega de lentes.
 *
 * `recibido_por` ES TEXTO Y NO UN ID DE USUARIO
 * --------------------------------------------
 * Las opticas entregan a un familiar, a un represente o por retiro de un
 * tercero, y ninguno de ellos tiene cuenta en el sistema. Exigir una cuenta
 * seria inventar un dato que la optica no tiene.
 *
 * Entregar de verdad tiene otra regla, y no vive aca: el pedido tiene que
 * haber pasado el control de calidad. La comprueba
 * App\Policies\EntregaPolicy::entregar.
 */
class RegistrarEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Entrega::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pedido_id' => ['required', 'uuid'],
            'fecha_registro' => ['required', 'date', 'before_or_equal:today'],
            'fecha_entrega' => ['nullable', 'date', 'before_or_equal:today'],
            'recibido_por' => ['nullable', 'string', 'max:160'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Una entrega hecha tiene que tener fecha.
     *
     * Sin fecha no se puede ordenar la cola de ajustes, que es justamente para
     * lo que existe. La base tambien lo comprueba, con una restriccion CHECK.
     */
    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador): void {
            $fecha = $this->input('fecha_entrega');

            if ($fecha !== null && $fecha !== '') {
                return;
            }

            if ($this->boolean('entregado') === true) {
                $validador->errors()->add(
                    'fecha_entrega',
                    'Una entrega marcada como hecha necesita su fecha.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_entrega.before_or_equal' => 'La entrega no puede ser en el futuro.',
        ];
    }
}