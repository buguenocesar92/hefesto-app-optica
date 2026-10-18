<?php

declare(strict_types=1);

namespace App\Services\Recetas;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Estado de vigencia de una receta, ya calculado.
 *
 * El resultado se guarda en la tabla de recetas como JSON, no como columnas:
 * `vigente` y `vencida` NO son estados guardados, se calculan cada vez que se
 * pregunta. Ver la nota de App\Enums\EstadoReceta.
 *
 * La razon de que sea un objeto y no un booleano es que la respuesta nunca es
 * solo si o no. Hay una tercera respuesta que importa: NO SE SABE, porque la
 * receta no tiene fecha de vencimiento y ademas el plazo legal todavia no esta
 * verificado. Ver docs/06.
 */
final class Vencimiento
{
    /**
     * @param  string  $estado  `vigente`, `por_vencer`, `vencida`, `sin_plazo`
     *                          o `anulada`.
     * @param  CarbonImmutable|null  $fechaVencimiento  Fecha cargada en la
     *                                                 receta, si hay.
     * @param  int|null  $diasRestantes  Dias que faltan. Negativo si ya
     *                                    vencio. Null si no hay fecha.
     * @param  int|null  $edadAnios  Edad cumplida al momento de calcular.
     * @param  string|null  $franja  Etiqueta de la franja etaria.
     * @param  int|null  $plazoDias  Dias de vigencia que propone la
     *                               configuracion para esa franja. Null si
     *                               todavia no se verifico.
     * @param  bool  $pendienteDeVerificacion  Si el plazo legal todavia no
     *                                          esta verificado en esta
     *                                          instalacion.
     */
    private function __construct(
        public readonly string $estado,
        public readonly ?CarbonImmutable $fechaVencimiento,
        public readonly ?int $diasRestantes,
        public readonly ?int $edadAnios,
        public readonly ?string $franja,
        public readonly ?int $plazoDias,
        public readonly bool $pendienteDeVerificacion,
        public readonly ?string $explicacion,
    ) {}

    /**
     * @param  int|null  $edadAnios
     */
    public static function vigente(
        CarbonImmutable $fechaVencimiento,
        ?int $diasRestantes,
        ?int $edadAnios,
        ?string $franja,
        ?int $plazoDias,
        bool $pendiente,
    ): self {
        return new self(
            'vigente',
            $fechaVencimiento,
            $diasRestantes,
            $edadAnios,
            $franja,
            $plazoDias,
            $pendiente,
            null,
        );
    }

    /**
     * Vence pronto: todavia sirve, pero ya entra en el aviso de
     * `config('optica.receta.dias_de_aviso')`.
     *
     * @param  int|null  $edadAnios
     */
    public static function porVencer(
        CarbonImmutable $fechaVencimiento,
        int $diasRestantes,
        ?int $edadAnios,
        ?string $franja,
        ?int $plazoDias,
        bool $pendiente,
    ): self {
        return new self(
            'por_vencer',
            $fechaVencimiento,
            $diasRestantes,
            $edadAnios,
            $franja,
            $plazoDias,
            $pendiente,
            'Esta receta vence pronto. Confirme con el paciente si necesita un control antes de despachar.',
        );
    }

    /**
     * @param  int|null  $edadAnios
     */
    public static function vencida(
        CarbonImmutable $fechaVencimiento,
        int $diasRestantes,
        ?int $edadAnios,
        ?string $franja,
        ?int $plazoDias,
        bool $pendiente,
    ): self {
        return new self(
            'vencida',
            $fechaVencimiento,
            $diasRestantes,
            $edadAnios,
            $franja,
            $plazoDias,
            $pendiente,
            'Esta receta ya vencio. Despachar lentes con una receta vencida no corresponde.',
        );
    }

    /**
     * No hay fecha de vencimiento, y ademas el plazo legal no esta
     * verificado en esta instalacion.
     *
     * Esta es la situacion por omision en una instalacion nueva, y es una
     * situacion HONESTA: el sistema dice que no sabe, en vez de inventar un
     * plazo.
     *
     * @param  int|null  $edadAnios
     */
    public static function sinPlazo(
        ?int $edadAnios,
        ?string $franja,
        ?int $plazoDias,
        bool $pendiente,
    ): self {
        $explicacion = $pendiente
            ? 'Esta receta no tiene fecha de vencimiento porque el plazo legal por franja etaria '
                .'todavia no se ha verificado contra la norma vigente. Ver docs/06 y docs/07.'
            : 'Esta receta no tiene fecha de vencimiento cargada.';

        return new self(
            'sin_plazo',
            null,
            null,
            $edadAnios,
            $franja,
            $plazoDias,
            $pendiente,
            $explicacion,
        );
    }

    /**
     * La receta fue anulada o reemplazada: el estado de cuenta no aplica.
     */
    public static function anulada(string $estadoReceta): self
    {
        return new self(
            'anulada',
            null,
            null,
            null,
            null,
            null,
            false,
            'La receta esta en estado '.$estadoReceta.' y no se usa para despachar lentes.',
        );
    }

    /**
     * Si la receta se puede usar para despachar lentes hoy.
     *
     * `sin_plazo` DEVUELVE `true`, y es la decision importante. Bloquear el
     * despacho cuando el plazo legal todavia no esta verificado dejaria a la
     * optica sin poder trabajar: no podria atender a nadie hasta que alguien
     * definiera una norma que no hemos verificado. El sistema avisa y deja
     * decidir a la persona, que es quien responde por la decision.
     */
    public function permiteDespachar(): bool
    {
        return in_array($this->estado, ['vigente', 'por_vencer', 'sin_plazo'], true);
    }

    /**
     * Para guardar en la receta y para mandar a la interfaz.
     *
     * @return array<string, mixed>
     */
    public function aArray(): array
    {
        return [
            'estado' => $this->estado,
            'fecha_vencimiento' => $this->fechaVencimiento?->format('Y-m-d'),
            'dias_restantes' => $this->diasRestantes,
            'edad_anios' => $this->edadAnios,
            'franja_etaria' => $this->franja,
            'plazo_dias' => $this->plazoDias,
            'pendiente_de_verificacion' => $this->pendienteDeVerificacion,
            'permite_despachar' => $this->permiteDespachar(),
            'explicacion' => $this->explicacion,
        ];
    }

    /**
     * Cuenta los años cumplidos en una fecha de referencia.
     *
     * Se cuenta por años cumplidos y no dividiendo dias: el reparto simple de
     * 365 dias por año hace que un niño de 2 años y 364 dias aparezca como de
     * 3, y lo manda a la franja equivocada.
     */
    public static function edadEnAnios(?CarbonInterface $fechaNacimiento, CarbonInterface $referencia): ?int
    {
        if ($fechaNacimiento === null) {
            return null;
        }

        if ($fechaNacimiento->isAfter($referencia)) {
            // Fecha de nacimiento en el futuro: no hay edad que calcular.
            return null;
        }

        return (int) $fechaNacimiento->diffInYears($referencia);
    }
}