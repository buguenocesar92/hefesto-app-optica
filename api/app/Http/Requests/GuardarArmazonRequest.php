<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta y edicion de un armazon del catalogo.
 *
 * LAS TRES MEDIDAS SON OBLIGATORIAS
 * --------------------------------
 * Sin ancho, puente y alto el armazon no sirve para el calculo de compatibilidad,
 * y un armazon guardado sin medidas es un armazon que va a fallar en el taller.
 * Es preferible no cargarlo antes que cargarlo a medias.
 *
 * No se pone un rango inventado: las medidas se comparan contra cero, que es
 * el unico limite que no depende de una norma. Un armazon de 400 mm de ancho
 * se cuela, y es preferible a inventar un maximo que podria rechazar un
 * armazon valido.
 */
class GuardarArmazonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Armazon::class) ?? false;
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
            'modelo' => ['nullable', 'string', 'max:120'],
            'material' => ['nullable', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:80'],
            'ancho_varilla_mm' => ['required', 'numeric', 'gt:0'],
            'puente_mm' => ['required', 'numeric', 'gt:0'],
            'alto_mm' => ['required', 'numeric', 'gt:0'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ancho_varilla_mm.gt' => 'El ancho de varilla debe ser mayor que cero.',
            'puente_mm.gt' => 'El puente debe ser mayor que cero.',
            'alto_mm.gt' => 'El alto debe ser mayor que cero.',
        ];
    }
}