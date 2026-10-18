<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta y edicion de un lente del catalogo.
 *
 * `indice_refraccion` Y `diametro_corte_mm` SON NULLABLES, A PROPOSITO
 * ---------------------------------------------------------------------
 * Los valores de indice que ofrece cada fabricante cambian segun el modelo y
 * el mercado, y el diametro de mecanizado depende de que pastilla tenga cada
 * laboratorio. No se puede verificar ningun valor aca, asi que se deja el hueco.
 *
 * Lo que pasa mientras el diametro este vacio: la verificacion de
 * compatibilidad devuelve `no_verificable`, con el desglose de lo que si se pudo
 * calcular. No devuelve "compatible" por omision, porque afirmar sin el dato
 * es adivinar. Ver docs/06.
 */
class GuardarLenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Lente::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:60'],
            'nombre' => ['required', 'string', 'max:160'],
            'marca' => ['nullable', 'string', 'max:120'],
            'material' => ['nullable', 'string', 'max:80'],
            'indice_refraccion' => ['nullable', 'numeric', 'gt:0'],
            'tratamientos' => ['nullable', 'array'],
            'tratamientos.*' => ['string', 'max:80'],
            'diametro_corte_mm' => ['nullable', 'numeric', 'gt:0'],
            'es_progresivo' => ['sometimes', 'boolean'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'diametro_corte_mm.gt' => 'El diametro de mecanizado debe ser mayor que cero.',
            'indice_refraccion.gt' => 'El indice de refraccion debe ser mayor que cero.',
        ];
    }
}