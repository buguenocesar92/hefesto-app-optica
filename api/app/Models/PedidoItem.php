<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Ojo;
use App\Models\Concerns\PerteneceAOptica;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Renglon de un pedido: un ojo.
 *
 * `graduacion_pedida` y `graduacion_recibida` son COPIAS en JSON de la
 * graduacion, no referencias a `graduaciones`.
 *
 * La razon es que un pedido es un documento: si se guardara solo el
 * `receta_id`, bastaria con que alguien corrigiera la receta despues de
 * enviado el pedido para que el control de calidad comparara lo recibido
 * contra algo que nunca se fabrico. Copiando, se compara contra lo que
 * realmente salio de la optica. Ver docs/02.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $pedido_laboratorio_id
 * @property Ojo $ojo
 * @property string|null $lente_id
 * @property int $cantidad
 * @property array<string, mixed>|null $graduacion_pedida
 * @property array<string, mixed>|null $graduacion_recibida
 */
class PedidoItem extends Model
{
    /** @use HasFactory<\Database\Factories\PedidoItemFactory> */
    use HasFactory;
    use HasUuids;
    use PerteneceAOptica;

    protected $table = 'pedido_items';

    protected $fillable = [
        'pedido_laboratorio_id',
        'ojo',
        'lente_id',
        'cantidad',
        'graduacion_pedida',
        'graduacion_recibida',
    ];

    protected function casts(): array
    {
        return [
            'ojo' => Ojo::class,
            'cantidad' => 'integer',
            'graduacion_pedida' => 'array',
            'graduacion_recibida' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PedidoLaboratorio, $this>
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoLaboratorio::class, 'pedido_laboratorio_id');
    }

    /**
     * @return BelongsTo<Lente, $this>
     */
    public function lente(): BelongsTo
    {
        return $this->belongsTo(Lente::class);
    }

    /**
     * Si ya se cargo lo que llego del laboratorio.
     */
    public function tieneGraduacionRecibida(): bool
    {
        return is_array($this->graduacion_recibida) && $this->graduacion_recibida !== [];
    }

    /**
     * El item en la forma que espera el comparador de calidad.
     *
     * @return array{ojo: string, pedido: array<string, mixed>|null, recibido: array<string, mixed>|null}
     */
    public function paraComparar(): array
    {
        return [
            'ojo' => $this->ojo->value,
            'pedido' => $this->graduacion_pedida,
            'recibido' => $this->graduacion_recibida,
        ];
    }
}