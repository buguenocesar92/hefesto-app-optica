<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Carga de las graduaciones de una receta: una fila por ojo y por meridiano.
 *
 * LA ESTRUCTURA SE VALIDA ACA Y TAMBIEN EN LA BASE
 * -----------------------------------------------
 * La migracion de `graduaciones` tiene restricciones CHECK que garantizan que
 * no exista un eje fuera de rango, ni una adicion en el meridiano equivocado,
 * ni un eje distinto de cero con cilindro cero. Esta clase evita que la
 * aplicacion llegue a intentarlo; la base es la que no deja pasar el error.
 *
 * LAS CUATRO FILAS SON OBLIGATORIAS
 * ---------------------------------
 * Una receta sin los dos ojos por los dos meridianos esta incompleta, y una
 * receta incompleta despacha lentes incompletos. Se exige la grilla completa
 * por los dos ojos y los dos meridianos.
 */
class GuardarGraduacionesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('receta')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'graduaciones' => ['required', 'array', 'count:4'],
            'graduaciones.*.ojo' => ['required', 'in:derecho,izquierdo'],
            'graduaciones.*.meridiano' => ['required', 'in:lejos,cerca'],
            'graduaciones.*.esfera' => ['required', 'numeric'],
            'graduaciones.*.cilindro' => ['required', 'numeric'],
            'graduaciones.*.eje' => ['required', 'integer', 'between:0,180'],
            'graduaciones.*.adiccion' => ['nullable', 'numeric'],
        ];
    }

    /**
     * Comprueba la grilla: dos ojos por dos meridianos, sin repetir.
     *
     * La unicidad ya la impone el indice unico de la base
     * `(receta_id, ojo, meridiano)`, pero avisar aca evita un error 500 en vez
     * de un mensaje util.
     */
    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador): void {
            $filas = $this->input('graduaciones');

            if (! is_array($filas)) {
                return;
            }

            $vistas = [];

            foreach ($filas as $fila) {
                if (! is_array($fila)) {
                    continue;
                }

                $clave = ($fila['ojo'] ?? '').'|'.($fila['meridiano'] ?? '');

                if (isset($vistas[$clave])) {
                    $validador->errors()->add(
                        'graduaciones',
                        'Hay mas de una fila para '.$fila['ojo'].' en '.$fila['meridiano'].'.'
                    );
                }

                $vistas[$clave] = true;

                /*
                | La adicion solo va en el meridiano de cerca, y sin asterisco no
                | es nula. Las dos cosas las prohibe la base con CHECK; aca se
                | avisa antes de llegar.
                */
                $esCerca = ($fila['meridiano'] ?? '') === 'cerca';
                $adiccion = $fila['adiccion'] ?? null;

                if ($esCerca && ($adiccion === null || $adiccion === '')) {
                    $validador->errors()->add(
                        'graduaciones',
                        'Falta la adicion en el meridiano de cerca de '.$fila['ojo'].'.'
                    );
                }

                if (! $esCerca && $adiccion !== null && $adiccion !== '') {
                    $validador->errors()->add(
                        'graduaciones',
                        'La adicion solo se carga en el meridiano de cerca, no en el de lejos.'
                    );
                }

                /*
                | Sin astigmatismo no hay eje. Si el cilindro es cero, el eje
                | tiene que ser cero tambien.
                */
                $cilindro = (float) ($fila['cilindro'] ?? 0);
                $eje = (int) ($fila['eje'] ?? 0);

                if (abs($cilindro) < 0.0001 && $eje !== 0) {
                    $validador->errors()->add(
                        'graduaciones',
                        'Con cilindro en cero el eje debe ser cero tambien. Ojo: '.$fila['ojo']
                            .', meridiano '.$fila['meridiano'].'.'
                    );
                }
            }

            $faltan = [];

            foreach (['derecho', 'izquierdo'] as $ojo) {
                foreach (['lejos', 'cerca'] as $meridiano) {
                    if (! isset($vistas[$ojo.'|'.$meridiano])) {
                        $faltan[] = $ojo.' en '.$meridiano;
                    }
                }
            }

            if ($faltan !== []) {
                $validador->errors()->add(
                    'graduaciones',
                    'Falta cargar: '.implode(', ', $faltan).'. Una receta completa lleva los dos ojos por los dos meridianos.'
                );
            }
        });
    }
}