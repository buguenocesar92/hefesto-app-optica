<?php

declare(strict_types=1);

namespace App\Services\Optica;

use App\Enums\VeredictoCompatibilidad;

/**
 * Resultado de verificar si un armazon y un lente encajan juntos.
 *
 * Devuelve el DESGLOSE DEL CALCULO, no un si o un no.
 *
 * La razon es que el optico tiene que poder decidir, no solo obedecer:
 * cuando el veredicto es `incompatible` o `no_verificable`, lo que hace falta
 * es entender que numero salio mal. Por eso el desglose incluye las medidas de
 * caja del armazon, el margen que se aplico, el diametro que se pidio, el
 * que tiene el lente y cuanto sobra o falta.
 *
 * @see VerificadorDeCompatibilidad
 */
final class ResultadoCompatibilidad
{
    /**
     * @param  VeredictoCompatibilidad  $veredicto  Que paso.
     * @param  array<string, mixed>  $desglose  Los numeros del calculo.
     * @param  string|null  $motivo  Por que, en palabras. Solo cuando el
     *                                veredicto no es afirmativo.
     */
    private function __construct(
        public readonly VeredictoCompatibilidad $veredicto,
        public readonly array $desglose,
        public readonly ?string $motivo,
    ) {}

    /**
     * @param  array<string, mixed>  $desglose
     */
    public static function compatible(array $desglose): self
    {
        return new self(VeredictoCompatibilidad::Compatible, $desglose, null);
    }

    /**
     * @param  array<string, mixed>  $desglose
     */
    public static function incompatible(array $desglose, string $motivo): self
    {
        return new self(VeredictoCompatibilidad::Incompatible, $desglose, $motivo);
    }

    /**
     * @param  array<string, mixed>  $desglose
     */
    public static function noVerificable(array $desglose, string $motivo): self
    {
        return new self(VeredictoCompatibilidad::NoVerificable, $desglose, $motivo);
    }

    /**
     * El lente entra con holgura. Unico veredicto que despacha sin revisar.
     */
    public function esCompatible(): bool
    {
        return $this->veredicto === VeredictoCompatibilidad::Compatible;
    }

    /**
     * Falta un dato para poder afirmar nada, asi que no hay veredicto.
     */
    public function esNoVerificable(): bool
    {
        return $this->veredicto === VeredictoCompatibilidad::NoVerificable;
    }

    /**
     * Que dato falta, si es que falta alguno.
     *
     * @return array<int, string>
     */
    public function datosFaltantes(): array
    {
        $faltantes = [];

        if (($this->desglose['lente']['diametro_corte_mm'] ?? null) === null) {
            $faltantes[] = 'diametro_corte_mm';
        }

        if (($this->desglose['margen_efectivo_minimo_mm'] ?? null) === null) {
            $faltantes[] = 'margen_efectivo_minimo_mm';
        }

        return $faltantes;
    }

    /**
     * Para guardar en la columna JSON del pedido y para mandar a la interfaz.
     *
     * @return array<string, mixed>
     */
    public function aArray(): array
    {
        return [
            'veredicto' => $this->veredicto->value,
            'veredicto_etiqueta' => $this->veredicto->etiqueta(),
            'motivo' => $this->motivo,
            'datos_faltantes' => $this->datosFaltantes(),
            'desglose' => $this->desglose,
        ];
    }
}