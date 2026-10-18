<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Recepcion de un pedido: que llego de vuelta del laboratorio.
 *
 * `graduacion_recibida` SE ESCRIBE TAL COMO LLEGO, NO CORREGIDA
 * ------------------------------------------------------------
 * La razon es el control de calidad: si al copiar la graduacion que llego se
 * "acomoda" cualquier valor, el comparador nunca va a encontrar nada y la
 * revision deja de servir para nada. Se guarda lo que dice el rotulo del lente.
 */
class RecibirPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('pedido')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha_recepcion' => ['required', 'date', 'before_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ojo' => ['required', 'in:derecho,izquierdo'],
            'items.*.graduacion_recibida' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador): void {
            $items = $this->input('items');

            if (! is_array($items)) {
                return;
            }

            $ojos = [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $ojo = (string) ($item['ojo'] ?? '');

                if (isset($ojos[$ojo])) {
                    $validador->errors()->add(
                        'items',
                        'Hay mas de un renglon para el mismo ojo. Cada ojo va una vez.'
                    );
                }

                $ojos[$ojo] = true;

                $graduacion = $item['graduacion_recibida'] ?? null;

                if (! is_array($graduacion)) {
                    continue;
                }

                foreach ($graduacion as $meridiano => $valores) {
                    if (! in_array($meridiano, ['lejos', 'cerca'], true)) {
                        $validador->errors()->add(
                            'items',
                            'Meridiano desconocido: '.$meridiano.'. Solo puede ser lejos o cerca.'
                        );

                        continue;
                    }

                    if (! is_array($valores)) {
                        continue;
                    }

                    if (isset($valores['eje']) && ! is_numeric($valores['eje'])) {
                        $validador->errors()->add(
                            'items',
                            'El eje de '.$ojo.' en '.$meridiano.' no es un numero.'
                        );
                    }
                }
            }
        });
    }
}