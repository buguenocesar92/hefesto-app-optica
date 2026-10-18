<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Registro de un ajuste posterior a la entrega.
 *
 * `entrega_id` TIENE QUE SER DE UNA ENTREGA YA HECHA
 * --------------------------------------------------
 * Ajustar antes de entregar no tiene sentido: el armazon recien comprado se
 * ajusta en el momento de la entrega, y queda registrado como tal. La
 * comprobacion la hace App\Policies\AjustePolicy.
 *
 * NO HAY DIAS DE GARANTIA QUE VALIDAR
 * ----------------------------------
 * Porque no hay plazo legal verificable. Los dias de garantia sugeridos estan
 * en `config('optica.entrega.dias_de_garantia_sugeridos')` y NACEN EN NULL.
 * Poner un campo obligatorio con un valor inventado seria exactamente el error
 * que este producto evita. Ver docs/07.
 */
class RegistrarAjusteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Ajuste::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entrega_id' => ['required', 'uuid'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'motivo' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'estado' => ['sometimes', 'in:pendiente,realizado,no_requerido'],
            'lente_sustituido' => ['sometimes', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Motivos que la interfaz sugiere.
     *
     * No son una lista cerrada: el campo acepta texto libre, porque la forma
     * de trabajar cambia de optica a optica. Esto es solo lo que se ofrece.
     *
     * @return list<string>
     */
    public static function motivosSugeridos(): array
    {
        return [
            'El armazon aprieta',
            'El armazon se desliza',
            'Los lentes quedan muy altos',
            'Los lentes quedan muy bajos',
            'Se nota el aro del lente',
            'Distorsión en la periferia',
            'Molestia al mirar de cerca',
            'Molestia al mirar de lejos',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Describa el motivo del ajuste.',
            'fecha.before_or_equal' => 'La fecha del ajuste no puede ser futura.',
        ];
    }
}