<?php

declare(strict_types=1);

namespace App\Services\Recetas;

use App\Enums\EstadoReceta;
use App\Models\Paciente;
use App\Models\Receta;
use Carbon\CarbonImmutable;

/**
 * Calcula si una receta esta vigente, y propone el plazo cuando falta.
 *
 * EL PLAZO ES NORMA, NO CONVENCION
 * -------------------------------
 * Una receta optica tiene plazo de vigencia, y ese plazo depende de la edad
 * del paciente: en la gente joven la graduacion cambia mas rapido, y por eso
 * el plazo que le vale a un adulto no le vale a un nino.
 *
 * El plazo concreto por franja etaria es NORMA SANITARIA CHILENA. En la
 * maquina donde se escribio esta aplicacion NO se pudo verificar contra la
 * norma vigente cual es ese plazo. Por eso TODAS las franjas de
 * `config('optica.receta.vigencia_por_edad')` nacen con `dias => null`.
 *
 * Que hace este servicio mientras tanto
 * ------------------------------------
 *   - Si la receta ya tiene `fecha_vencimiento`, la respeta y solo informa.
 *   - Si no la tiene, devuelve `sin_plazo` con la franja y la edad, y la
 *     interfaz avisa que el plazo esta pendiente de verificacion.
 *   - Si algun dia se configura `OPTICA_VIGENCIA_..._ANIOS`, el mismo codigo
 *     propone la fecha, sin cambiar una linea.
 *
 * NUNCA inventa una fecha. Preferimos el hueco al dato inventado. Ver docs/06.
 */
class CalculadoraDeVencimiento
{
    /**
     * Calcula el estado de vigencia de una receta.
     *
     * @param  Receta  $receta  La receta, con `paciente` ya cargado.
     * @param  CarbonImmutable|null  $hoy  Fecha de referencia. Por defecto,
     *                                    hoy en la zona horaria de la
     *                                    instalacion. Se puede pasar para
     *                                    pruebas.
     */
    public function calcular(Receta $receta, ?CarbonImmutable $hoy = null): Vencimiento
    {
        $hoy ??= CarbonImmutable::now(config('optica.zona_horaria'));

        if (in_array($receta->estado, EstadoReceta::queNoAutorizanPedido(), true)) {
            return Vencimiento::anulada($receta->estado);
        }

        $paciente = $receta->paciente;

        $edad = $this->edad($paciente, $hoy);
        $franja = $this->franjaPara($edad);
        $plazo = $franja['dias'] ?? null;

        /*
        | Si no hay ninguna franja configurada con plazo, el sistema no sabe
        | cuanto vale. Se dice, en vez de devolver un numero.
        */
        $pendiente = ! $this->hayAlgunPlazoConfigurado();

        $fechaVencimiento = $receta->fecha_vencimiento === null
            ? null
            : CarbonImmutable::parse($receta->fecha_vencimiento, config('optica.zona_horaria'));

        // Sin fecha cargada no hay nada que comparar contra hoy.
        if ($fechaVencimiento === null) {
            return Vencimiento::sinPlazo($edad, $franja['etiqueta'] ?? null, $plazo, $pendiente);
        }

        $diasRestantes = (int) $hoy->startOfDay()->diffInDays(
            $fechaVencimiento->startOfDay(),
            false
        );

        if ($diasRestantes < 0) {
            return Vencimiento::vencida(
                $fechaVencimiento,
                $diasRestantes,
                $edad,
                $franja['etiqueta'] ?? null,
                $plazo,
                $pendiente,
            );
        }

        $diasAviso = (int) config('optica.receta.dias_de_aviso', 30);

        if ($diasRestantes <= $diasAviso) {
            return Vencimiento::porVencer(
                $fechaVencimiento,
                $diasRestantes,
                $edad,
                $franja['etiqueta'] ?? null,
                $plazo,
                $pendiente,
            );
        }

        return Vencimiento::vigente(
            $fechaVencimiento,
            $diasRestantes,
            $edad,
            $franja['etiqueta'] ?? null,
            $plazo,
            $pendiente,
        );
    }

    /**
     * Propone la fecha de vencimiento a partir de la configuracion.
     *
     * Devuelve `null` si el plazo de la franja del paciente no esta
     * configurado. Quien llama decide que hacer con un `null`: la aplicacion
     * guarda la receta sin fecha y avisa, no rellena con una fecha inventada.
     */
    public function proponerFechaVencimiento(
        Paciente $paciente,
        CarbonImmutable $fechaEmision,
    ): ?CarbonImmutable {
        $hoy = CarbonImmutable::now(config('optica.zona_horaria'));

        $franja = $this->franjaPara(Vencimiento::edadEnAnios($paciente->fecha_nacimiento, $hoy));

        $dias = $franja['dias'] ?? null;

        if ($dias === null) {
            return null;
        }

        return $fechaEmision->addDays((int) $dias);
    }

    /**
     * Si hay al menos una franja con plazo cargado.
     */
    public function hayAlgunPlazoConfigurado(): bool
    {
        foreach ($this->franjas() as $franja) {
            if (($franja['dias'] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Las franjas tal como estan configuradas, para mostrarlas en la
     * administracion: la persona ve que hay una franja sin plazo y por que.
     *
     * @return list<array{etiqueta: string, desde: int, hasta: int, dias: int|null}>
     */
    public function franjas(): array
    {
        $crudas = config('optica.receta.vigencia_por_edad', []);

        $franjas = [];

        foreach ($crudas as $franja) {
            $franjas[] = [
                'etiqueta' => (string) ($franja['etiqueta'] ?? 'Sin etiqueta'),
                'desde' => (int) ($franja['desde'] ?? 0),
                'hasta' => (int) ($franja['hasta'] ?? 0),
                'dias' => ($franja['dias'] ?? null) === null ? null : (int) $franja['dias'],
            ];
        }

        return $franjas;
    }

    /**
     * Edad cumplida del paciente en la fecha de referencia.
     */
    private function edad(Paciente $paciente, CarbonImmutable $hoy): ?int
    {
        if ($paciente->fecha_nacimiento === null) {
            return null;
        }

        return Vencimiento::edadEnAnios(
            CarbonImmutable::parse($paciente->fecha_nacimiento),
            $hoy,
        );
    }

    /**
     * La franja etaria que corresponde a una edad.
     *
     * @return array{etiqueta?: string, desde?: int, hasta?: int, dias?: int|null}
     */
    private function franjaPara(?int $edad): array
    {
        if ($edad === null) {
            return [];
        }

        foreach ($this->franjas() as $franja) {
            if ($edad >= $franja['desde'] && $edad <= $franja['hasta']) {
                return $franja;
            }
        }

        // Edad fuera de toda franja configurada (por ejemplo, alguien de 150
        // anios, o una franja mal cargada). Se dice que no se sabe en vez de
        // forzar la mas cercana.
        return [];
    }
}