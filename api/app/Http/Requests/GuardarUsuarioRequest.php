<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de una cuenta de usuario en la optica.
 *
 * EL ROL SE IGNORA A PROPOSITO
 * ---------------------------
 * El rol no se elige aca: lo decide Authentik segun los equipos a los que
 * pertenezca la persona, y se resuelve en cada ingreso (ver
 * App\Services\Auth\ResolutorDeSesion).
 *
 * Lo que hace este endpoint es dejar COSTEAR la cuenta, para que la primera
 * vez que la persona entre no falle por no existir en la base. Es un atajo, no
 * una fuente de verdad: si el rol guardado aqui no coincide con el de Authentik,
 * manda Authentik.
 *
 * NO HAY CONTRASENA
 * ----------------
 * Ni campo, ni columna, ni parametro. Ver docs/07.
 */
class GuardarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Usuario::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:160'],
            'correo' => ['required', 'string', 'email', 'max:190'],
            'rol' => ['sometimes', 'in:mostrador,optometrista,administrador'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'correo.email' => 'El correo no tiene el formato esperado.',
            'correo.required' => 'El correo es obligatorio: es la llave con la que se vincula la cuenta de Authentik.',
            'rol.in' => 'El rol no es valido.',
        ];
    }
}