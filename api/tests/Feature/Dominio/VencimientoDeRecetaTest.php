<?php

declare(strict_types=1);

namespace Tests\Feature\Dominio;

use App\Models\Optica;
use App\Models\Paciente;
use App\Models\Receta;
use App\Models\Usuario;
use App\Services\Recetas\CalculadoraDeVencimiento;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vencimiento de la receta.
 *
 * LO QUE ESTA PRUEBA PROTEGE
 * --------------------------
 * Que el sistema NO invente plazos legales.
 *
 * El plazo de vigencia de una receta optica por franja etaria es norma
 * sanitaria chilena. En la maquina donde se escribio esta aplicacion no se pudo
 * verificar contra la norma vigente, asi que TODAS las franjas de
 * `config/optica.php` nacen con `dias => null`.
 *
 * Con el plazo sin configurar, el comportamiento correcto es devolver
 * `sin_plazo` y avisar. Si estas pruebas se adaptaran para esperar una fecha,
 * estarian validando un invento. Ver docs/06 y docs/10.
 */
class VencimientoDeRecetaTest extends TestCase
{
    use RefreshDatabase;

    private CalculadoraDeVencimiento $calculadora;

    private Optica $optica;

    private Usuario $optometrista;

    protected function setUp(): void
    {
        parent::setUp();

        // El estado por omision de la instalacion es NO SABER. Se deja explicito
        // para que la prueba no dependa del archivo .env de la maquina.
        config([
            'optica.receta.vigencia_por_edad' => $this->franjasSinPlazo(),
            'optica.receta.dias_de_aviso' => 30,
        ]);

        $this->calculadora = new CalculadoraDeVencimiento();

        $this->optica = Optica::factory()->create();

        $this->optometrista = $this->enOptica(
            $this->optica->id,
            fn (): Usuario => Usuario::factory()->optometrista()->create()
        );
    }

    protected function tearDown(): void
    {
        config(['optica.receta.vigencia_por_edad' => $this->franjasSinPlazo()]);

        parent::tearDown();
    }

    public function test_sin_plazo_configurado_ninguna_fraccion_declara_dias(): void
    {
        $this->assertFalse(
            $this->calculadora->hayAlgunPlazoConfigurado(),
            'La instalacion recien creada no debe declarar ningun plazo legal.'
        );
    }

    public function test_una_receta_sin_fecha_devuelve_sin_plazo_y_no_inventa_una_fecha(): void
    {
        $receta = $this->recetaConPaciente(['fecha_nacimiento' => '1985-05-10']);

        $vencimiento = $this->enOptica(
            $this->optica->id,
            fn () => $this->calculadora->calcular($receta)
        );

        $this->assertSame('sin_plazo', $vencimiento->estado);
        $this->assertNull($vencimiento->fechaVencimiento);
        $this->assertNull($vencimiento->plazoDias);
        $this->assertTrue($vencimiento->pendienteDeVerificacion);
        $this->assertNotNull($vencimiento->explicacion);
    }

    public function test_la_edad_sirve_para_saber_en_que_fraccion_cae_el_paciente(): void
    {
        /*
        | Aunque no haya plazo, el sistema si sabe la edad y en que franja cae.
        | Eso es lo que permite decir "el plazo de esta franja todavia no esta
        | verificado" en vez de un "no se" generico.
        */
        $receta = $this->recetaConPaciente(['fecha_nacimiento' => now()->subYears(9)->toDateString()]);

        $vencimiento = $this->enOptica(
            $this->optica->id,
            fn () => $this->calculadora->calcular($receta)
        );

        $this->assertSame(9, $vencimiento->edadAnios);
        $this->assertSame('Hasta los 12 anios', $vencimiento->franja);
        $this->assertNull($vencimiento->plazoDias);
    }

    public function test_sin_fecha_de_nacimiento_no_hay_franja(): void
    {
        $receta = $this->recetaConPaciente(['fecha_nacimiento' => null]);

        $vencimiento = $this->enOptica(
            $this->optica->id,
            fn () => $this->calculadora->calcular($receta)
        );

        $this->assertNull($vencimiento->edadAnios);
        $this->assertNull($vencimiento->franja);
        $this->assertSame('sin_plazo', $vencimiento->estado);
    }

    public function test_sin_plazo_la_receta_aun_puede_despacharse(): void
    {
        /*
        | Esta es una decision de producto y hay que hacerla explicita.
        |
        | Bloquear el despacho mientras el plazo legal no este verificado
        | dejaria a la optica sin poder atender a nadie hasta que alguien
        | definiera una norma que no hemos verificado. El sistema avisa y deja
        | decidir a la persona, que es quien responde por la decision.
        */
        $receta = $this->recetaConPaciente();

        $vencimiento = $this->enOptica(
            $this->optica->id,
            fn () => $this->calculadora->calcular($receta)
        );

        $this->assertTrue($vencimiento->permiteDespachar());
    }

    public function test_con_fecha_cargada_el_sistema_solo_informa(): void
    {
        $receta = $this->recetaConPaciente(
            ['fecha_nacimiento' => '1985-05-10'],
            ['fecha_vencimiento' => now()->addMonths(6)->toDateString()]
        );

        $vencimiento = $this->enOptica(
            $this->optica->id,
            fn () => $this->calculadora->calcular($receta)
        );

        $this->assertSame('vigente', $vencimiento->estado);
        $this->assertGreaterThan(30, $vencimiento->diasRestantes);
        $this->assertTrue($vencimiento->permiteDespachar());
    }

    public function test_una_receta_vencida_no_permite_despachar(): void
    {
        $receta = $this->recetaConPaciente(
            ['fecha_nacimiento' => '1985-05-10'],
            ['fecha_vencimiento' => now()->subMonth()->toDateString()]
        );

        $vencimiento = $this->enOptica(
            $this->optica->id,
            fn () => $this->calculadora->calcular($receta)
        );

        $this->assertSame('vencida', $vencimiento->estado);
        $this->assertLessThan(0, $vencimiento->diasRestantes);
        $this->assertFalse($vencimiento->permiteDespachar());
    }

    public function test_una_receta_por_vencer_avisa_dentro_del_plazo_de_aviso(): void
    {
        config(['optica.receta.dias_de_aviso' => 30]);

        $receta = $this->recetaConPaciente(
            ['fecha_nacimiento' => '1985-05-10'],
            ['fecha_vencimiento' => now()->addDays(10)->toDateString()]
        );

        $vencimiento = $this->enOptica(
            $this->optica->id,
            fn () => $this->calculadora->calcular($receta)
        );

        $this->assertSame('por_vencer', $vencimiento->estado);
        $this->assertTrue($vencimiento->permiteDespachar());
    }

    public function test_una_receta_anulada_no_entra_al_calculo_de_vigencia(): void
    {
        $receta = $this->recetaConPaciente(
            ['fecha_nacimiento' => '1985-05-10'],
            ['fecha_vencimiento' => now()->addYear()->toDateString()],
            'anulada'
        );

        $vencimiento = $this->enOptica(
            $this->optica->id,
            fn () => $this->calculadora->calcular($receta)
        );

        $this->assertSame('anulada', $vencimiento->estado);
        $this->assertFalse($vencimiento->permiteDespachar());
    }

    public function test_la_edad_se_calcula_por_anios_cumplidos(): void
    {
        /*
        | Dividir dias entre 365 haria que un nino de 2 años y 364 dias
        | apareciera como de 3, y lo mandaria a la franja equivocada.
        */
        $hoy = CarbonImmutable::parse('2026-10-18');

        $casiTres = CarbonImmutable::parse('2023-10-19');

        $this->assertSame(2, \App\Services\Recetas\Vencimiento::edadEnAnios($casiTres, $hoy));

        $recienTres = CarbonImmutable::parse('2023-10-18');

        $this->assertSame(3, \App\Services\Recetas\Vencimiento::edadEnAnios($recienTres, $hoy));
    }

    public function test_una_fecha_de_nacimiento_futura_no_calcula_edad(): void
    {
        $hoy = CarbonImmutable::parse('2026-10-18');

        $futura = CarbonImmutable::parse('2027-01-01');

        $this->assertNull(\App\Services\Recetas\Vencimiento::edadEnAnios($futura, $hoy));
    }

    public function test_con_plazo_configurado_se_propone_la_fecha(): void
    {
        /*
        | Cuando la optica ya verifico su plazo, el MISMO codigo propone la
        | fecha, sin cambiar una linea. Esta es la parte que hace que el hueco
        | sea un hueco y no una pared.
        */
        config([
            'optica.receta.vigencia_por_edad' => [
                ['etiqueta' => 'Hasta los 12 anios', 'desde' => 0, 'hasta' => 12, 'dias' => 180],
                ['etiqueta' => 'De 13 a 17 anios', 'desde' => 13, 'hasta' => 17, 'dias' => null],
                ['etiqueta' => 'De 18 a 59 anios', 'desde' => 18, 'hasta' => 59, 'dias' => 730],
                ['etiqueta' => 'De 60 anios y mas', 'desde' => 60, 'hasta' => 200, 'dias' => null],
            ],
        ]);

        $this->assertTrue($this->calculadora->hayAlgunPlazoConfigurado());

        // Un nino cae en la primera franja, que si tiene plazo.
        $nino = $this->enOptica(
            $this->optica->id,
            fn (): Paciente => Paciente::factory()->create(['fecha_nacimiento' => '2018-01-01'])
        );

        $propuesta = $this->calculadora->proponerFechaVencimiento($nino, CarbonImmutable::parse('2026-10-18'));

        $this->assertNotNull($propuesta);
        $this->assertSame('2027-04-16', $propuesta->format('Y-m-d'));

        // Un adolescente cae en una franja que sigue sin verificar.
        $adolescente = $this->enOptica(
            $this->optica->id,
            fn (): Paciente => Paciente::factory()->create(['fecha_nacimiento' => '2010-01-01'])
        );

        $this->assertNull(
            $this->calculadora->proponerFechaVencimiento($adolescente, CarbonImmutable::parse('2026-10-18'))
        );
    }

    /**
     * Las franjas con todos los plazos en null: el estado por omision.
     *
     * @return list<array{etiqueta: string, desde: int, hasta: int, dias: int|null}>
     */
    private function franjasSinPlazo(): array
    {
        return [
            ['etiqueta' => 'Hasta los 12 anios', 'desde' => 0, 'hasta' => 12, 'dias' => null],
            ['etiqueta' => 'De 13 a 17 anios', 'desde' => 13, 'hasta' => 17, 'dias' => null],
            ['etiqueta' => 'De 18 a 59 anios', 'desde' => 18, 'hasta' => 59, 'dias' => null],
            ['etiqueta' => 'De 60 anios y mas', 'desde' => 60, 'hasta' => 200, 'dias' => null],
        ];
    }

    /**
     * @param  array<string, mixed>  $atributosPaciente
     * @param  array<string, mixed>  $atributosReceta
     */
    private function recetaConPaciente(
        array $atributosPaciente = ['fecha_nacimiento' => '1985-05-10'],
        array $atributosReceta = [],
        string $estado = 'emitida',
    ): Receta {
        return $this->enOptica($this->optica->id, function () use (
            $atributosPaciente,
            $atributosReceta,
            $estado
        ): Receta {
            $paciente = Paciente::factory()->create($atributosPaciente);

            return Receta::factory()->create([
                'paciente_id' => $paciente->id,
                'optometrista_id' => $this->optometrista->id,
                'estado' => $estado,
                ...$atributosReceta,
            ]);
        });
    }
}