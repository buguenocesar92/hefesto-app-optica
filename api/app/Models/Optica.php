<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una optica: la empresa, o sea el tenant.
 *
 * ESTA TABLA NO LLEVA ROW LEVEL SECURITY
 * -------------------------------------
 * Se consulta en el selector de ingreso, antes de que exista sesion y por lo
 * tanto antes de que exista `app.tenant_id`. Como compensacion, lo que se
 * expone al navegador no es esta tabla sino la vista `vw_opticas_publicas`,
 * que deja pasar tres columnas. Ver docs/03.
 *
 * @property string $id
 * @property string $nombre
 * @property string $slug
 * @property string $correlacion_identidad
 * @property string $zona_horaria
 * @property bool $activo
 */
class Optica extends Model
{
    /** @use HasFactory<\Database\Factories\OpticaFactory> */
    use HasFactory;
    use HasUuids;

    protected $table = 'opticas';

    protected $fillable = [
        'nombre',
        'slug',
        'correlacion_identidad',
        'zona_horaria',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * LAS OPTICAS QUE SE PUEDEN MOSTRAR EN EL INGRESO.
     *
     * Esta es la compensacion de que `opticas` no tenga RLS, y SE USA DE
     * VERDAD: la consume GET /api/v1/acceso/opticas, que es lo que llena el
     * selector de la pantalla de entrada (web/app/pages/entrar.vue).
     *
     * Se lee la VISTA y no la tabla, a proposito: la vista tiene tres columnas
     * y filtra las opticas inactivas. Si alguien cambiara esta consulta por
     * `Optica::query()->where('activo', true)`, el selector pasaria a exponer
     * tambien `correlacion_identidad`, que es la llave que liga a una persona
     * con una optica en Authentik. Ver docs/03.
     *
     * @return list<array{id: string, slug: string, nombre: string}>
     */
    public static function publicas(): array
    {
        return static::query()
            ->select(['id', 'slug', 'nombre'])
            ->from('vw_opticas_publicas')
            ->orderBy('nombre')
            ->get()
            ->map(static fn (self $optica): array => [
                'id' => (string) $optica->getAttribute('id'),
                'slug' => (string) $optica->getAttribute('slug'),
                'nombre' => (string) $optica->getAttribute('nombre'),
            ])
            ->all();
    }

    /**
     * @return HasMany<Usuario, $this>
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'tenant_id');
    }

    /**
     * @return HasMany<Paciente, $this>
     */
    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class, 'tenant_id');
    }

    /**
     * @return HasMany<Receta, $this>
     */
    public function recetas(): HasMany
    {
        return $this->hasMany(Receta::class, 'tenant_id');
    }

    /**
     * Fecha en la zona horaria de esta optica.
     *
     * Guardar fechas en hora de Chile y no en UTC es lo comodo; lo correcto es
     * guardar en UTC y convertir al mostrar. Por eso `recetas.fecha_vencimiento`
     * es una columna DATE sin zona: es un dia, no un instante.
     */
    public function hoy(): Carbon
    {
        return Carbon::now($this->zona_horaria);
    }
}