<?php

declare(strict_types=1);

namespace Tests\Feature\Aislamiento;

use App\Models\Optica;
use App\Models\Paciente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Throwable;

/**
 * Aislamiento entre opticas, a nivel de base de datos.
 *
 * ESTA SUITE NECESITA PostgreSQL
 * -----------------------------
 * La RLS es una funcion de PostgreSQL. Con SQLite estas pruebas pasarian sin
 * comprobar nada: un sistema sin aislamiento alguno tambien daria verde.
 * Ver phpunit.xml y docs/10.
 *
 * LO QUE COMPRUEBA
 * ----------------
 *   1. Que las tablas de negocio tengan RLS ACTIVADO y FORZADO.
 *   2. Que una optica NO lea, NO escriba y NO vea que existen filas de otra.
 *   3. Que una consulta SIN contexto FALLE, en vez de devolver filas.
 *
 * El punto 3 es el que mas importa: el aislamiento tiene que fallar cerrado.
 */
class AislamientoEntreOpticasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Las mismas tablas de la migracion 2026_10_18_000015.
     *
     * @var list<string>
     */
    private const TABLAS_PROTEGIDAS = [
        'usuarios',
        'pacientes',
        'recetas',
        'graduaciones',
        'armazones',
        'lentes',
        'pedidos_laboratorio',
        'pedido_items',
        'controles_calidad',
        'entregas',
        'ajustes',
        'accesos_recetas',
    ];

    public function test_todas_las_tablas_de_negocio_tienen_rls_forzado(): void
    {
        foreach (self::TABLAS_PROTEGIDAS as $tabla) {
            $fila = \Illuminate\Support\Facades\DB::selectOne(
                'SELECT relrowsecurity AS rls, relforcerowsecurity AS forzado
                 FROM pg_class WHERE relname = ?',
                [$tabla]
            );

            $this->assertNotNull($fila, "La tabla {$tabla} no existe.");
            $this->assertTrue((bool) $fila->rls, "La tabla {$tabla} no tiene RLS activado.");
            $this->assertTrue(
                (bool) $fila->forzado,
                "La tabla {$tabla} no tiene FORCE ROW LEVEL SECURITY. Sin FORCE, el dueno la saltaria."
            );
        }
    }

    public function test_la_tabla_de_opticas_no_tiene_rls(): void
    {
        $fila = \Illuminate\Support\Facades\DB::selectOne(
            'SELECT relrowsecurity AS rls FROM pg_class WHERE relname = ?',
            ['opticas']
        );

        $this->assertFalse((bool) $fila->rls);
    }

    public function test_una_optica_no_ve_los_pacientes_de_otra(): void
    {
        $opticaA = Optica::factory()->create();
        $opticaB = Optica::factory()->create();

        $pacienteB = $this->enOptica($opticaB->id, fn (): Paciente => Paciente::factory()->create([
            'nombres' => 'Belinda',
            'apellidos' => 'DeLaOpticaB',
        ]));

        $vistos = $this->enOptica($opticaA->id, fn (): int => Paciente::query()
            ->whereKey($pacienteB->id)
            ->count());

        $this->assertSame(0, $vistos, 'La optica A alcanzo a ver un paciente de la optica B.');
    }

    public function test_una_optica_no_ve_los_pacientes_de_otra_aunque_no_filtre_por_id(): void
    {
        $opticaA = Optica::factory()->create();
        $opticaB = Optica::factory()->create();

        $this->enOptica($opticaB->id, fn (): Paciente => Paciente::factory()->create([
            'apellidos' => 'InvisibleParaA',
        ]));

        $totalEnA = $this->enOptica($opticaA->id, fn (): int => Paciente::query()->count());

        $this->assertSame(0, $totalEnA, 'La optica A vio filas que no son suyas.');
    }

    public function test_no_se_puede_insertar_una_fila_en_otra_optica(): void
    {
        $opticaA = Optica::factory()->create();
        $opticaB = Optica::factory()->create();

        $this->expectException(Throwable::class);

        $this->enOptica($opticaA->id, function () use ($opticaB): void {
            /*
            | Se intenta crear un paciente dentro del contexto de A pero
            | identificado como de B. WITH CHECK tiene que rechazarlo.
            */
            Paciente::query()->create([
                'tenant_id' => $opticaB->id,
                'nombres' => 'Intruso',
                'apellidos' => 'DeLaOpticaB',
            ]);
        });
    }

    public function test_sin_contexto_de_optica_la_consulta_falla(): void
    {
        Optica::factory()->create();

        $this->expectException(Throwable::class);

        $this->sinOptica(fn (): int => Paciente::query()->count());
    }

    public function test_sin_contexto_no_se_puede_insertar(): void
    {
        $this->expectException(Throwable::class);

        $this->sinOptica(function (): void {
            Paciente::query()->create([
                'nombres' => 'SinContexto',
                'apellidos' => 'NiDeOpticaNiDeContexto',
            ]);
        });
    }

    public function test_el_contexto_desaparece_al_terminar_la_transaccion(): void
    {
        $opticaA = Optica::factory()->create();

        $this->enOptica($opticaA->id, function (): void {
            $this->assertNotNull($this->opticaEnConexion());
        });

        /*
        | Esta es la propiedad que evita que el worker de colas arrastre la
        | optica de una empresa a la siguiente: `set_config(..., true)` es de
        | alcance de transaccion y PostgreSQL lo descarta al confirmar.
        */
        $this->assertNull(
            $this->opticaEnConexion(),
            'El contexto de optica sobrevivio a la transaccion. El worker de colas podria leer datos de otra optica.'
        );
    }
}