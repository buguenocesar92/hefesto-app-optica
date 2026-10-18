<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta y edicion de un paciente.
 *
 * EL RUT ES TEXTO
 * ---------------
 * Se valida como cadena, no como numero: como numero se pierden los ceros a la
 * izquierda y el digito verificador, y un RUT mal cargado no sirve para
 * facturar. La validacion acepta el formato con y sin puntos, y
 * `prepareForValidation` lo deja siempre igual.
 */
class GuardarPacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rut' => ['nullable', 'string', 'max:16', 'regex:/^\d{1,2}\.?\d{3}\.?\d{3}-?\d$/'],
            'nombres' => ['required', 'string', 'max:120'],
            'apellidos' => ['required', 'string', 'max:120'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'telefono' => ['nullable', 'string', 'max:32'],
            'correo' => ['nullable', 'string', 'email', 'max:190'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Deja el RUT en un formato unico, con puntos y guion.
     */
    protected function prepareForValidation(): void
    {
        $rut = $this->input('rut');

        if (! is_string($rut) || trim($rut) === '') {
            return;
        }

        $soloNumeros = preg_replace('/\D/', '', $rut);

        if (! is_string($soloNumeros) || strlen($soloNumeros) < 2) {
            return;
        }

        $cuerpo = substr($soloNumeros, 0, -1);
        $verificador = substr($soloNumeros, -1);

        $this->merge([
            'rut' => vsprintf('%d.%03d.%03d-%s', [
                (int) substr($cuerpo, 0, -6),
                (int) substr($cuerpo, -6, 3),
                (int) substr($cuerpo, -3),
                $verificador,
            ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rut.regex' => 'El RUT no tiene el formato esperado. Ejemplo: 12.345.678-9',
            'fecha_nacimiento.before' => 'La fecha de nacimiento no puede ser futura.',
            'nombres.required' => 'El nombre del paciente es obligatorio.',
            'apellidos.required' => 'El apellido del paciente es obligatorio.',
        ];
    }
}