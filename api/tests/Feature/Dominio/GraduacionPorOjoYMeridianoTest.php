<?php

declare(strict_types=1);

namespace Tests\Feature\Dominio;

use App\Enums\Meridiano;
use App\Enums\Ojo;
use App\Models\Graduacion;
use App\Models\Optica;
use App\Models\Paciente;
use App\Models\Receta;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Estructura de la graduacion por ojo y por meridiano.
 *
 * UNA RECETA COMPLETA SON CUATRO FILAS
 * -----------------------------------
 * Dos ojos por dos meridianos. No columnas con sufijo `_lejos` y `_cerca`.
 * Ver la migracion de `graduaciones` para el por que.
 *
 * LO QUE ESTA PRUEBA PROTEGE
 * --------------------------
 * Que la estructura la imponga la BASE y no solo la aplicacion. Si el eje
 * estuviera fuera de rango o la adicion en el meridiano equivocado, la fila no
 * tiene que poder existir, ni siquiera con un INSERT escrito a mano.
 *
 * En una optica, un error de estos se descubre cuando el cliente ya se puso
 * los lentes. Por eso la restriccion va en la base.
 */
class GraduacionPorOjoYMeridianoTest extends TestCase
{
    use RefreshDatabase;

    private Optica $optica;

    private Receta $receta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->optica = Optica::factory()->create();

        $this->receta = $this->enOptica($this->optica->id, function (): Receta {
            $optometrista = Usuario::factory()->optometrista()->create();
            $paciente = Paciente::factory()->create();

            return Receta::factory()->create([
                'paciente_id' => $paciente->id,
                'optometrista_id' => $optometrista->id,
            ]);
        });
    }

    public function test_una_receta_completa_tiene_cuatro_filas(): void
    {
        $this->cargarGraduacionCompleta();

        $this->assertSame(4, $this->receta->graduaciones()->count());

        $combos = $this->receta->graduaciones()
            ->get()
            ->map(static fn (Graduacion $g): string => $g->ojo->value.'|'.$g->meridiano->value)
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'derecho|cerca',
            'derecho|lejos',
            'izquierdo|cerca',
            'izquierdo|lejos',
        ], $combos);
    }

    public function test_los_dos_ojos_pueden_tener_valores_distintos(): void
    {
        $this->cargarGraduacionCompleta();

        $derechoLejos = $this->graduacion('derecho', 'lejos');
        $izquierdoLejos = $this->graduacion('izquierdo', 'lejos');

        $this->assertSame(-2.25, $derechoLejos->esfera);
        $this->assertSame(-1.75, $izquierdoLejos->esfera);
    }

    public function test_la_adicion_solo_vive_en_el_meridiano_de_cerca(): void
    {
        $this->cargarGraduacionCompleta();

        $cerca = $this->graduacion('derecho', 'cerca');
        $lejos = $this->graduacion('derecho', 'lejos');

        $this->assertNotNull($cerca->adiccion);
        $this->assertSame(1.5, $cerca->adiccion);

        $this->assertNull($lejos->adiccion, 'La adicion no puede estar en el meridiano de lejos.');
    }

    public function test_no_se_puede_repetir_ojo_y_meridiano(): void
    {
        $this->cargarGraduacionCompleta();

        $this->expectException(QueryException::class);

        $this->enOptica($this->optica->id, function (): void {
            Graduacion::factory()->create([
                'receta_id' => $this->receta->id,
                'ojo' => Ojo::Derecho->value,
                'meridiano' => Meridiano::Lejos->value,
                'esfera' => -9.0,
                'cilindro' => 0,
                'eje' => 0,
            ]);
        });
    }

    public function test_la_adicion_no_puede_cargarse_en_el_meridiano_de_lejos(): void
    {
        $this->expectException(QueryException::class);

        $this->enOptica($this->optica->id, function (): void {
            Graduacion::factory()->create([
                'receta_id' => $this->receta->id,
                'ojo' => Ojo::Derecho->value,
                'meridiano' => Meridiano::Lejos->value,
                'esfera' => -2.25,
                'cilindro' => 0,
                'eje' => 0,
                'adiccion' => 1.5,
            ]);
        });
    }

    public function test_el_meridiano_de_cerca_exige_adicion(): void
    {
        $this->expectException(QueryException::class);

        $this->enOptica($this->optica->id, function (): void {
            Graduacion::factory()->create([
                'receta_id' => $this->receta->id,
                'ojo' => Ojo::Derecho->value,
                'meridiano' => Meridiano::Cerca->value,
                'esfera' => -1.25,
                'cilindro' => 0,
                'eje' => 0,
                'adiccion' => null,
            ]);
        });
    }

    public function test_un_eje_distinto_de_cero_con_cilindro_cero_no_puede_existir(): void
    {
        /*
        | Sin astigmatismo no hay eje. Un eje de 180 con cilindro 0 no
        | significa nada, y si llega a imprimirse en una receta hace pensar que
        | hay algo que corregir.
        */
        $this->expectException(QueryException::class);

        $this->enOptica($this->optica->id, function (): void {
            Graduacion::factory()->create([
                'receta_id' => $this->receta->id,
                'ojo' => Ojo::Derecho->value,
                'meridiano' => Meridiano::Lejos->value,
                'esfera' => -2.25,
                'cilindro' => 0,
                'eje' => 180,
            ]);
        });
    }

    public function test_un_eje_fuera_de_rango_no_puede_existir(): void
    {
        $this->expectException(QueryException::class);

        $this->enOptica($this->optica->id, function (): void {
            Graduacion::factory()->create([
                'receta_id' => $this->receta->id,
                'ojo' => Ojo::Derecho->value,
                'meridiano' => Meridiano::Lejos->value,
                'esfera' => -2.25,
                'cilindro' => -0.5,
                'eje' => 181,
            ]);
        });
    }

    public function test_un_ojo_desconocido_no_puede_existir(): void
    {
        $this->expectException(QueryException::class);

        $this->enOptica($this->optica->id, function (): void {
            Graduacion::factory()->create([
                'receta_id' => $this->receta->id,
                'ojo' => 'izq',
                'meridiano' => Meridiano::Lejos->value,
                'esfera' => -2.25,
                'cilindro' => 0,
                'eje' => 0,
            ]);
        });
    }

    public function test_con_astigmatismo_el_eje_se_guarda(): void
    {
        $this->cargarGraduacionCompleta();

        $conAstigmatismo = $this->enOptica($this->optica->id, function (): Graduacion {
            $this->receta->graduaciones()
                ->where('ojo', Ojo::Derecho->value)
                ->where('meridiano', Meridiano::Lejos->value)
                ->update(['cilindro' => -0.75, 'eje' => 180]);

            return $this->receta->graduaciones()
                ->where('ojo', Ojo::Derecho->value)
                ->where('meridiano', Meridiano::Lejos->value)
                ->first();
        });

        $this->assertSame(-0.75, $conAstigmatismo->cilindro);
        $this->assertSame(180, $conAstigmatismo->eje);
        $this->assertTrue($conAstigmatismo->tieneAstigmatismo());
    }

    public function test_la_graduacion_se_puede_volcar_a_la_foto_del_pedido(): void
    {
        $this->cargarGraduacionCompleta();

        $cerca = $this->graduacion('derecho', 'cerca');
        $foto = $cerca->aComponentes();

        $this->assertArrayHasKey('esfera', $foto);
        $this->assertArrayHasKey('cilindro', $foto);
        $this->assertArrayHasKey('eje', $foto);
        $this->assertArrayHasKey('adiccion', $foto);

        // En el meridiano de lejos la adicion no se copia.
        $lejos = $this->graduacion('derecho', 'lejos')->aComponentes();

        $this->assertArrayNotHasKey('adiccion', $lejos);
    }

    public function test_las_graduaciones_se_muestran_ordenadas_por_ojo_y_meridiano(): void
    {
        $this->cargarGraduacionCompleta();

        $orden = array_map(
            static fn (Graduacion $g): string => $g->ojo->value.'|'.$g->meridiano->value,
            $this->receta->graduacionesOrdenadas()
        );

        $this->assertSame([
            'derecho|lejos',
            'derecho|cerca',
            'izquierdo|lejos',
            'izquierdo|cerca',
        ], $orden);
    }

    /**
     * Carga las cuatro filas de una receta completa.
     */
    private function cargarGraduacionCompleta(): void
    {
        $this->enOptica($this->optica->id, function (): void {
            Graduacion::factory()->create([
                'receta_id' => $this->receta->id,
                'ojo' => Ojo::Derecho->value,
                'meridiano' => Meridiano::Lejos->value,
                'esfera' => -2.25,
                'cilindro' => 0,
                'eje' => 0,
                'adiccion' => null,
            ]);

            Graduacion::factory()->create([
                'receta_id' => $this->receta->id,
                'ojo' => Ojo::Derecho->value,
                'meridiano' => Meridiano::Cerca->value,
                'esfera' => -1.25,
                'cilindro' => 0,
                'eje' => 0,
                'adiccion' => 1.5,
            ]);

            Graduacion::factory()->create([
                'receta_id' => $this->receta->id,
                'ojo' => Ojo::Izquierdo->value,
                'meridiano' => Meridiano::Lejos->value,
                'esfera' => -1.75,
                'cilindro' => 0,
                'eje' => 0,
                'adiccion' => null,
            ]);

            Graduacion::factory()->create([
                'receta_id' => $this->receta->id,
                'ojo' => Ojo::Izquierdo->value,
                'meridiano' => Meridiano::Cerca->value,
                'esfera' => -0.75,
                'cilindro' => 0,
                'eje' => 0,
                'adiccion' => 1.5,
            ]);
        });
    }

    private function graduacion(string $ojo, string $meridiano): Graduacion
    {
        return $this->enOptica(
            $this->optica->id,
            fn (): Graduacion => $this->receta->graduaciones()
                ->where('ojo', $ojo)
                ->where('meridiano', $meridiano)
                ->firstOrFail()
        );
    }
}