<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Registro del control de calidad de un pedido recibido.
 *
 * EL VEREDICTO LO PONE UNA PERSONA
 * -------------------------------
 * El sistema compara valor por valor y devuelve las diferencias, pero NO
 * decide si son aceptables: la tolerancia con que se acepta una diferencia
 * (`config('optica.control_calidad.tolerancia_dioptrias')`) NACE EN NULL porque
 * es una decision clinica del optometrista, no una constante del software.
 *
 * El campo `diferencias` NO SE MANDA DESDE EL NAVEGADOR: lo arma el servidor
 * con App\Services\Calidad\ComparadorDeGraduaciones. Si el navegador mandara
 * las suyas, un formulario alterado podria declarar conforme un pedido que
 * llego con la graduacion equivocada, que es justo lo que este registro existe
 * para evitar.
 */
class RegistrarControlCalidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\ControlCalidad::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pedido_id' => ['required', 'uuid'],
            'resultado' => ['required', 'in:conforme,con_diferencias,no_conforme'],
            'fecha_revision' => ['required', 'date', 'before_or_equal:today'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Un `no_conforme` con diferencias vacias es una contradiccion.
     *
     * Pasa cuando la persona marca "no conforme" sin explicar que leyo. Se
     * pide la observacion para que el reclamo, si lo hay, tenga algo con que
     * trabajar.
     */
    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador): void {
            if ($this->input('resultado') !== 'no_conforme') {
                return;
            }

            $observaciones = $this->input('observaciones');

            if (! is_string($observaciones) || trim($observaciones) === '') {
                $validador->errors()->add(
                    'observaciones',
                    'Si el pedido no es conforme, deje escrito que se encontro y que se va a hacer.'
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
            'resultado.in' => 'El resultado del control de calidad no es valido.',
        ];
    }
}