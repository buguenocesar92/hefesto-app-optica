<?php

declare(strict_types=1);

namespace App\Services\Calidad;

/**
 * Compara la graduacion que se pidio al laboratorio con la que llego de vuelta.
 *
 * POR QUE EXISTE
 * --------------
 * Si el laboratorio manda la graduacion equivocada, el momento de enterarse es
 * ANTES de entregarle los lentes al cliente. Despues no hay reclamo que
 * solver: el cliente ya se los puso. Esta clase es la que hace la comparacion
 * valor por valor, y es la que se ejecuta al recibir el pedido.
 *
 * QUE COMPARA, CAMPO POR CAMPO
 * -----------------------------
 * Esfera, cilindro y eje, en los dos ojos y en los dos meridianos. Mas la
 * adicion en el meridiano de cerca. Se comparan los tres porque los tres se
 * pueden equivocar por separado: es facil que el laboratorio lea bien la
 * esfera y pase el eje.
 *
 * QUE NO HACE
 * -----------
 * NO emite un veredicto de conforme o no conforme. Solo dice que valores no
 * cuadran.
 *
 * La razon es que la tolerancia con que se acepta una diferencia
 * (`config('optica.control_calidad.tolerancia_dioptrias')`) NACE EN NULL: es
 * una decision clinica del optometrista, y hay tolerancias que el laboratorio
 * considera correctas y que el profesional no acepta. Mientras sea NULL, un
 * veredicto automatico seria el software opinando como profesional. Ver
 * docs/06.
 */
class ComparadorDeGraduaciones
{
    /**
     * Campos que se comparan en cada meridiano.
     *
     * `adiccion` solo existe en el meridiano de cerca, asi que se compara
     * aparte. Ver la migracion de `graduaciones`.
     *
     * @var list<string>
     */
    private const CAMPOS = ['esfera', 'cilindro', 'eje'];

    /**
     * Compara dos graduaciones y devuelve las diferencias.
     *
     * Las dos vienen en la misma forma que la columna `graduacion_pedida` de
     * `pedido_items`:
     *
     *   {"lejos": {"esfera": -2.25, "cilindro": -0.50, "eje": 180},
     *    "cerca": {"esfera": -1.25, "cilindro": -0.50, "eje": 180,
     *              "adiccion": 1.00}}
     *
     * @param  array<string, mixed>  $pedida  Lo que salio de la optica.
     * @param  array<string, mixed>  $recibida  Lo que llego del laboratorio.
     * @param  string  $ojo  `derecho` o `izquierdo`, para poder ubicar la
     *                        diferencia.
     * @return list<array<string, mixed>>  Las diferencias, o lista vacia.
     */
    public function comparar(array $pedida, array $recibida, string $ojo): array
    {
        $diferencias = [];

        foreach (['lejos', 'cerca'] as $meridiano) {
            $valoresPedidos = $pedida[$meridiano] ?? null;
            $valoresRecibidos = $recibida[$meridiano] ?? null;

            // Un meridiano entero que no llego es la diferencia mas grave que
            // puede haber, y hay que decirla explicitamente: si no, el informe
            // sale vacio y parece que todo cuadra.
            if (! is_array($valoresPedidos)) {
                continue;
            }

            if (! is_array($valoresRecibidos)) {
                $diferencias[] = [
                    'ojo' => $ojo,
                    'meridiano' => $meridiano,
                    'campo' => 'meridiano_completo',
                    'pedido' => null,
                    'recibido' => null,
                    'diferencia' => null,
                ];

                continue;
            }

            $campos = $meridiano === 'cerca'
                ? array_merge(self::CAMPOS, ['adiccion'])
                : self::CAMPOS;

            foreach ($campos as $campo) {
                $valorPedido = $valoresPedidos[$campo] ?? null;
                $valorRecibido = $valoresRecibidos[$campo] ?? null;

                if (! $this->difieren($valorPedido, $valorRecibido)) {
                    continue;
                }

                $diferencias[] = [
                    'ojo' => $ojo,
                    'meridiano' => $meridiano,
                    'campo' => $campo,
                    'pedido' => $valorPedido,
                    'recibido' => $valorRecibido,
                    'diferencia' => $this->delta($valorPedido, $valorRecibido),
                ];
            }
        }

        return $diferencias;
    }

    /**
     * Compara los dos renglones de un pedido y junta todas las diferencias.
     *
     * @param  list<array{ojo: string, pedido: array<string, mixed>|null, recibido: array<string, mixed>|null}>  $items
     * @return list<array<string, mixed>>
     */
    public function compararItems(array $items): array
    {
        $diferencias = [];

        foreach ($items as $item) {
            if (! is_array($item['pedido'] ?? null)) {
                continue;
            }

            if (! is_array($item['recibido'] ?? null)) {
                $diferencias[] = [
                    'ojo' => $item['ojo'],
                    'meridiano' => null,
                    'campo' => 'graduacion_recibida',
                    'pedido' => null,
                    'recibido' => null,
                    'diferencia' => null,
                ];

                continue;
            }

            foreach ($this->comparar($item['pedido'], $item['recibido'], $item['ojo']) as $diferencia) {
                $diferencias[] = $diferencia;
            }
        }

        return $diferencias;
    }

    /**
     * Si dos valores de graduacion son distintos.
     *
     * La comparacion es por valor exacto y NO con tolerancia a proposito: la
     * tolerancia la decide la persona, no esta clase. Lo que si se normaliza es
     * el formato, porque `0.5` y `0.50` son el mismo numero y no deberían
     * aparecer como diferencia.
     */
    private function difieren(mixed $pedido, mixed $recibido): bool
    {
        if ($pedido === null && $recibido === null) {
            return false;
        }

        if ($pedido === null || $recibido === null) {
            return true;
        }

        if (is_numeric($pedido) && is_numeric($recibido)) {
            return abs((float) $pedido - (float) $recibido) > 0.0001;
        }

        return (string) $pedido !== (string) $recibido;
    }

    /**
     * La diferencia entre los dos valores, en el mismo tipo del pedido.
     */
    private function delta(mixed $pedido, mixed $recibido): mixed
    {
        if (is_numeric($pedido) && is_numeric($recibido)) {
            return round((float) $recibido - (float) $pedido, 2);
        }

        return null;
    }
}