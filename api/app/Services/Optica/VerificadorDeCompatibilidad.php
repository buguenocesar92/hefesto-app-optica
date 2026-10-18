<?php

declare(strict_types=1);

namespace App\Services\Optica;

use App\Models\Armazon;
use App\Models\Lente;

/**
 * Verifica si un armazon y un lente se pueden combinar.
 *
 * POR QUE ESTE SERVICIO EXISTE
 * ----------------------------
 * Es la verificacion que sostiene el producto. Un lente se mecaniza sobre una
 * pastilla circular y despues se monta en el armazon. Si la pastilla no
 * alcanza para la caja del armazon mas el borde que el armazon necesita, la
 * hecha no sirve: o el lente no entra, o entra y se ve cortado. Y ese error se
 * descubre en la mecanica del taller, con el cliente esperando.
 *
 * EL CALCULO
 * ----------
 * La pastilla es un circulo. La caja del armazon es un rectangulo. Para que el
 * circulo cubra el rectangulo con una holgura `m` por lado, el diametro tiene
 * que alcanzar en LOS DOS EJES:
 *
 *     ancho_de_caja = ancho_varilla + puente
 *     alto_de_caja  = alto
 *
 *     diametro_horizontal_requerido = ancho_de_caja + 2 * m
 *     diametro_vertical_requerido   = alto_de_caja  + 2 * m
 *     diametro_minimo_requerido     = el mayor de los dos
 *
 * Se toman los dos ejes porque la caja no es cuadrada: un armazon bajo y ancho
 * y otro alto y angosto piden pastillas distintas, y quedarse solo con el ancho
 * deja pasar la combinacion imposible.
 *
 * LO QUE NO SE CALCULA AQUI
 * -------------------------
 * El valor `m`, el margen efectivo minimo. Depende del tipo de lente y de la
 * distancia interpupilar del paciente, y varia entre fabricantes. NO se pudo
 * verificar en esta maquina que valor corresponde, asi que
 * `config('optica.compatibilidad.diametro_efectivo_minimo_mm')` NACE EN NULL.
 *
 * Mientras `m` sea NULL, este servicio devuelve `no_verificable`: NUNCA "si" ni
 * "no" sin el dato. Devolver "si" seria adivinar, y adivinar aca significa
 * fabricar un lente que no entra. Ver docs/06.
 *
 * El servicio es PURO: no toca la base de datos y no escribe nada. Quien guarda
 * el veredicto es el pedido, en App\Http\Controllers\Pedidos.
 */
class VerificadorDeCompatibilidad
{
    /**
     * Verifica la combinacion de un armazon y un lente.
     *
     * @param  Armazon  $armazon  Armazon con sus medidas de caja cargadas.
     * @param  Lente  $lente  Lente del catalogo.
     */
    public function verificar(Armazon $armazon, Lente $lente): ResultadoCompatibilidad
    {
        $margen = config('optica.compatibilidad.diametro_efectivo_minimo_mm');

        $desglose = $this->armazon($armazon, $margen);

        // 1. El lente no dice de que diametro se mecaniza: no hay nada que
        //    comparar. Se devuelve lo que si se pudo calcular.
        $diametroLente = $lente->diametro_corte_mm === null
            ? null
            : (float) $lente->diametro_corte_mm;

        $desglose['lente'] = [
            'codigo' => $lente->codigo,
            'nombre' => $lente->nombre,
            'material' => $lente->material,
            'indice_refraccion' => $lente->indice_refraccion === null
                ? null
                : (float) $lente->indice_refraccion,
            'es_progresivo' => (bool) $lente->es_progresivo,
            'diametro_corte_mm' => $diametroLente,
        ];

        if ($diametroLente === null) {
            return ResultadoCompatibilidad::noVerificable(
                $desglose,
                'El lente '.($lente->codigo ?? $lente->nombre)
                    .' no tiene cargado de que diametro se mecaniza. '
                    .'Sin ese dato no se puede afirmar que entre en el armazon.'
            );
        }

        // 2. El margen efectivo no esta configurado. La parte geometrica ya
        //    esta calculada y se devuelve, pero el veredicto no se puede dar.
        if ($margen === null) {
            return ResultadoCompatibilidad::noVerificable(
                $desglose,
                'Falta el margen efectivo minimo del lente ('
                    .'config optica.compatibilidad.diametro_efectivo_minimo_mm). '
                    .'El calculo geometrico del armazon esta hecho, pero sin ese margen '
                    .'no se puede afirmar que la pastilla cubra la caja.'
            );
        }

        $margen = (float) $margen;

        $anchoCaja = $desglose['ancho_de_caja_mm'];
        $altoCaja = $desglose['alto_de_caja_mm'];

        $requeridoHorizontal = $anchoCaja + (2 * $margen);
        $requeridoVertical = $altoCaja + (2 * $margen);
        $requerido = max($requeridoHorizontal, $requeridoVertical);
        $holgura = $diametroLente - $requerido;

        $desglose['margen_efectivo_minimo_mm'] = $margen;
        $desglose['diametro_horizontal_requerido_mm'] = $requeridoHorizontal;
        $desglose['diametro_vertical_requerido_mm'] = $requeridoVertical;
        $desglose['diametro_minimo_requerido_mm'] = $requerido;
        $desglose['eje_que_manda'] = $requeridoHorizontal >= $requeridoVertical
            ? 'ancho_de_caja'
            : 'alto_de_caja';
        $desglose['holgura_mm'] = $holgura;

        if ($holgura < 0) {
            return ResultadoCompatibilidad::incompatible(
                $desglose,
                'La pastilla del lente es '.number_format($diametroLente, 2, ',', '.')
                    .' mm y el armazon necesita al menos '
                    .number_format($requerido, 2, ',', '.').' mm. '
                    .'Faltan '.number_format(abs($holgura), 2, ',', '.')
                    .' mm de diametro: la hecha no alcanza.'
            );
        }

        return ResultadoCompatibilidad::compatible($desglose);
    }

    /**
     * Parte geometrica del desglose: lo que sale del armazon y no depende de
     * ningun dato pendiente.
     *
     * @return array<string, mixed>
     */
    private function armazon(Armazon $armazon, mixed $margen): array
    {
        $anchoVarilla = (float) $armazon->ancho_varilla_mm;
        $puente = (float) $armazon->puente_mm;
        $alto = (float) $armazon->alto_mm;

        return [
            'armazon' => [
                'codigo' => $armazon->codigo,
                'nombre' => $armazon->nombre,
                'ancho_varilla_mm' => $anchoVarilla,
                'puente_mm' => $puente,
                'alto_mm' => $alto,
            ],
            'ancho_de_caja_mm' => $anchoVarilla + $puente,
            'alto_de_caja_mm' => $alto,
            'margen_efectivo_minimo_mm' => $margen === null ? null : (float) $margen,
        ];
    }
}