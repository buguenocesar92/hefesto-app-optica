<?php

declare(strict_types=1);

namespace Tests\Feature\Aislamiento;

use App\Models\Optica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La vista `vw_opticas_publicas`, que compensa que `opticas` no tenga RLS.
 *
 * ESTA PRUEBA EXISTE PORQUE LA VISTA SE USA
 * ----------------------------------------
 * En aplicaciones anteriores se creo la vista y nunca se consulto, mientras la
 * documentacion afirmaba que si. Eso es prometer una proteccion que no existe.
 *
 * Por eso esta prueba no comprueba solo que la vista exista: comprueba que
 * expone EXACTAMENTE tres columnas, que no deja pasar `correlacion_identidad`,
 * y que `Optica::publicas()` la lee de verdad. Ver docs/03.
 */
class VistaOpticasPublicasTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_vista_existe(): void
    {
        $existe = \Illuminate\Support\Facades\DB::selectOne(
            "SELECT to_regclass('public.vw_opticas_publicas') AS vista"
        );

        $this->assertNotNull($existe->vista);
    }

    public function test_la_vista_expone_solo_tres_columnas(): void
    {
        $columnas = \Illuminate\Support\Facades\DB::select(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'vw_opticas_publicas'
             ORDER BY ordinal_position"
        );

        $nombres = array_map(static fn ($columna): string => $columna->column_name, $columnas);

        $this->assertSame(['id', 'slug', 'nombre'], $nombres);

        /*
        | `correlacion_identidad` es la llave que liga a una persona con una
        | optica en Authentik. Publicarla antes de autenticarse seria
        | entregarle a cualquiera el mapa de las empresas del sistema.
        */
        $this->assertNotContains('correlacion_identidad', $nombres);
        $this->assertNotContains('zona_horaria', $nombres);
    }

    public function test_la_vista_solo_muestra_opticas_activas(): void
    {
        Optica::factory()->create(['nombre' => 'Optica activa', 'activo' => true]);
        Optica::factory()->inactiva()->create(['nombre' => 'Optica apagada']);

        $nombres = array_column(Optica::publicas(), 'nombre');

        $this->assertContains('Optica activa', $nombres);
        $this->assertNotContains('Optica apagada', $nombres);
    }

    public function test_publicas_devuelve_las_tres_columnas_y_nada_mas(): void
    {
        Optica::factory()->create([
            'nombre' => 'Optica visible',
            'slug' => 'optica-visible',
            'correlacion_identidad' => 'secreto-de-identidad',
        ]);

        $publicas = Optica::publicas();

        $this->assertCount(1, $publicas);
        $this->assertSame(['id', 'slug', 'nombre'], array_keys($publicas[0]));

        // El dato que no debe salir por ninguna razon.
        $this->assertStringNotContainsString(
            'secreto-de-identidad',
            json_encode($publicas, JSON_THROW_ON_ERROR)
        );
    }

    public function test_publicas_se_ordena_por_nombre(): void
    {
        Optica::factory()->create(['nombre' => 'Zeta']);
        Optica::factory()->create(['nombre' => 'Alfa']);

        $nombres = array_column(Optica::publicas(), 'nombre');

        $this->assertSame($nombres, array_values(array_unique($nombres)));
        $this->assertSame('Alfa', $nombres[0]);
    }

    public function test_publicas_no_requiere_contexto_de_optica(): void
    {
        Optica::factory()->create(['nombre' => 'Optica sin contexto']);

        /*
        | `opticas` no tiene RLS justamente para que esto funcione: el selector
        | de ingreso corre antes de que exista sesion.
        */
        $this->assertNull($this->opticaEnConexion());

        $nombres = array_column($this->sinOptica(fn (): array => Optica::publicas()), 'nombre');

        $this->assertContains('Optica sin contexto', $nombres);
    }
}