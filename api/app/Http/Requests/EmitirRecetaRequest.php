<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Receta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Emision de una receta.
 *
 * LA FECHA DE VENCIMIENTO SE ACEPTA NULA, A PROPOSITO
 * --------------------------------------------------
 * El plazo legal por franja etaria todavia no se ha verificado contra la norma
 * vigente, asi que el sistema no propone ninguna. Si la persona escribe una, se
 * guarda; si no, la receta queda sin plazo y el motivo queda ahi al lado para
 * que quede constancia de que se emitio a sabiendas.
 *
 * Ver docs/06 y docs/07.
 */
class EmitirRecetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Receta::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paciente_id' => ['required', 'uuid'],
            'numero' => ['required', 'string', 'max:40'],
            'fecha_emision' => ['required', 'date', 'before_or_equal:today'],

            // Si se escribe, tiene que ser posterior a la emision. Lo
            // comprueba tambien la base, con una restriccion CHECK.
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_emision'],

            'motivo_sin_vencimiento' => ['nullable', 'string', 'max:2000'],
            'distancia_interpupilar_mm' => ['nullable', 'numeric', 'between:40,90'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * No se puede emitir sin fecha de vencimiento y sin decir por que.
     *
     * Sin este par, una receta sin plazo seria indistinguible de una receta a
     * la que se le olvido la fecha.
     */
    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador): void {
            $sinFecha = $this->input('fecha_vencimiento') === null
                || $this->input('fecha_vencimiento') === '';

            $sinMotivo = $this->input('motivo_sin_vencimiento') === null
                || trim((string) $this->input('motivo_sin_vencimiento')) === '';

            if ($sinFecha && $sinMotivo) {
                $validador->errors()->add(
                    'motivo_sin_vencimiento',
                    'Si la receta no tiene fecha de vencimiento, deje escrito por que se emite sin plazo.'
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
            'fecha_vencimiento.after_or_equal' => 'La receta no puede vencerse antes de emitirse.',
            'distancia_interpupilar_mm.between' => 'La distancia interpupilar no parece una medida en milimetros.',
        ];
    }
}