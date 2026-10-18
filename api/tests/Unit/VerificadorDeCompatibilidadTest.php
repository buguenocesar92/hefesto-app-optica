<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\VeredictoCompatibilidad;
use App\Models\Armazon;
use App\Models\Lente;
use App\Services\Optica\VerificadorDeCompatibilidad;
use Tests\TestCase;

/**
 * Verificacion de compatibilidad entre armazon y lente.
 *
 * ESTA ES LA PRUEBA QUE SOSTIENE EL PRODUCTO
 * ------------------------------------------
 * Si la combinacion no cabe, la hecha no sirve y el reclamo llega cuando el
 * cliente ya se puso los lentes. Ver docs/06.
 *
 * LO QUE ESTA PRUEBA COMPRUEBA, Y ES LO IMPORTANTE
 * -------------------------------------------------
 * Que cuando falta un dato, el servicio devuelve `no_verificable` y NO
 * "compatible". Ese es el comportamiento que distingue esta verificacion de
 * un simple si o no, y el que evita que el sistema adivine.
 *
 * OJO CON `config()`
 * ------------------
 * El margen efectivo se toma de `config('optica.compatibilidad...')`, que nace
 * en NULL. Las pruebas que necesitan un valor lo fijan con `Config::set` y lo
 * vuelven a dejar en null. phpunit.xml ademas fuerza las variables de entorno
 * de vigencia a vacio, para que el archivo `.env` de la maquina no pueda
 * colarse. Ver docs/10.
 */
class VerificadorDeCompatibilidadTest extends TestCase
{
    private VerificadorDeCompatibilidad $verificador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verificador = new VerificadorDeCompatibilidad();
    }

    protected function tearDown(): void
    {
        // El margen vuelve a NULL: el estado por omision del sistema es no
        // saber, y una prueba no puede dejarlo cambiado.
        config(['optica.compatibilidad.diametro_efectivo_minimo_mm' => null]);

        parent::tearDown();
    }

    public function test_sin_margen_configurado_no_se_puede_afirmar_nada(): void
    {
        config(['optica.compatibilidad.diametro_efectivo_minimo_mm' => null]);

        $resultado = $this->verificador->verificar($this->armazon(), $this->lente(80.0));

        $this->assertSame(
            VeredictoCompatibilidad::NoVerificable,
            $resultado->veredicto,
            'Sin el margen efectivo configurado, el servicio no puede afirmar que el lente entre.'
        );

        $this->assertTrue($resultado->esNoVerificable());
        $this->assertFalse($resultado->esCompatible());
        $this->assertContains('margen_efectivo_minimo_mm', $resultado->datosFaltantes());
    }

    public function test_la_parte_geometrica_se_calcula_aunque_falte_el_margen(): void
    {
        config(['optica.compatibilidad.diametro_efectivo_minimo_mm' => null]);

        $resultado = $this->verificador->verificar($this->armazon(), $this->lente(80.0));

        $desglose = $resultado->desglose;

        // 52 + 18 = 70.
        $this->assertSame(70.0, $desglose['ancho_de_caja_mm']);
        $this->assertSame(40.0, $desglose['alto_de_caja_mm']);
        $this->assertSame(52.0, $desglose['armazon']['ancho_varilla_mm']);
        $this->assertSame(18.0, $desglose['armazon']['puente_mm']);
    }

    public function test_lente_sin_diametro_de_corte_no_se_puede_verificar(): void
    {
        config(['optica.compatibilidad.diametro_efectivo_minimo_mm' => 5.0]);

        $lente = new Lente();
        $lente->codigo = 'LE-SIN-DIAMETRO';
        $lente->nombre = 'Lente sin diametro cargado';
        $lente->diametro_corte_mm = null;

        $resultado = $this->verificador->verificar($this->armazon(), $lente);

        $this->assertSame(VeredictoCompatibilidad::NoVerificable, $resultado->veredicto);
        $this->assertContains('diametro_corte_mm', $resultado->datosFaltantes());
    }

    public function test_pastilla_generosa_da_compatible_con_holgura(): void
    {
        config(['optica.compatibilidad.diametro_efectivo_minimo_mm' => 5.0]);

        // Caja de 70 x 40. Con margen 5 por lado se piden 80 y 50: manda 80.
        // Una pastilla de 95 entra sobrada.
        $resultado = $this->verificador->verificar($this->armazon(), $this->lente(95.0));

        $this->assertTrue($resultado->esCompatible(), $resultado->motivo ?? '');
        $this->assertSame(80.0, $resultado->desglose['diametro_minimo_requerido_mm']);
        $this->assertSame(80.0, $resultado->desglose['diametro_horizontal_requerido_mm']);
        $this->assertSame(50.0, $resultado->desglose['diametro_vertical_requerido_mm']);
        $this->assertSame('ancho_de_caja', $resultado->desglose['eje_que_manda']);
        $this->assertSame(15.0, $resultado->desglose['holgura_mm']);
    }

    public function test_pastilla_justa_da_compatible_sin_holgura(): void
    {
        config(['optica.compatibilidad.diametro_efectivo_minimo_mm' => 5.0]);

        $resultado = $this->verificador->verificar($this->armazon(), $this->lente(80.0));

        $this->assertTrue($resultado->esCompatible());
        $this->assertSame(0.0, $resultado->desglose['holgura_mm']);
    }

    public function test_pastilla_pequena_da_incompatible(): void
    {
        config(['optica.compatibilidad.diametro_efectivo_minimo_mm' => 5.0]);

        $resultado = $this->verificador->verificar($this->armazon(), $this->lente(72.0));

        $this->assertSame(VeredictoCompatibilidad::Incompatible, $resultado->veredicto);
        $this->assertSame(80.0, $resultado->desglose['diametro_minimo_requerido_mm']);
        $this->assertSame(-8.0, $resultado->desglose['holgura_mm']);
        $this->assertNotNull($resultado->motivo);
    }

    public function test_el_alto_puede_mandar_sobre_el_ancho(): void
    {
        config(['optica.compatibilidad.diametro_efectivo_minimo_mm' => 5.0]);

        /*
        | Armazon angosto y alto: 40 + 18 = 58 de ancho, pero 60 de alto.
        | Con margen 5 se piden 68 y 70: manda el ALTO, y una pastilla de 69
        | serviria para el ancho y no para el alto.
        |
        | Quedarse solo con el ancho, que es el error clasico, dejaria pasar
        | esta combinacion imposible.
        */
        $armazon = $this->armazon(ancho: 40.0, puente: 18.0, alto: 60.0);

        $resultado = $this->verificador->verificar($armazon, $this->lente(69.0));

        $this->assertSame('alto_de_caja', $resultado->desglose['eje_que_manda']);
        $this->assertSame(70.0, $resultado->desglose['diametro_minimo_requerido_mm']);
        $this->assertFalse($resultado->esCompatible());
    }

    public function test_solo_compatible_autoriza_despachar(): void
    {
        config(['optica.compatibilidad.diametro_efectivo_minimo_mm' => 5.0]);

        $compatible = $this->verificador->verificar($this->armazon(), $this->lente(95.0));
        $incompatible = $this->verificador->verificar($this->armazon(), $this->lente(72.0));
        $noVerificable = $this->verificador->verificar($this->armazon(), $this->lente(80.0));

        $this->assertTrue($compatible->veredicto->permiteDespachar());
        $this->assertFalse($incompatible->veredicto->permiteDespachar());

        /*
        | `no_verificable` NO despacha. Si despachara, el hueco del catalogo
        | dejaria pasar cualquier combinacion sin que nadie la mirara.
        */
        $this->assertFalse($noVerificable->veredicto->permiteDespachar());
    }

    public function test_el_desglose_para_guardar_trae_todo_el_calculo(): void
    {
        config(['optica.compatibilidad.diametro_efectivo_minimo_mm' => 5.0]);

        $array = $this->verificador->verificar($this->armazon(), $this->lente(95.0))->aArray();

        $this->assertSame('compatible', $array['veredicto']);
        $this->assertArrayHasKey('desglose', $array);
        $this->assertArrayHasKey('ancho_de_caja_mm', $array['desglose']);
        $this->assertArrayHasKey('diametro_minimo_requerido_mm', $array['desglose']);
        $this->assertArrayHasKey('holgura_mm', $array['desglose']);
        $this->assertArrayHasKey('diametro_corte_mm', $array['desglose']['lente']);
        $this->assertSame([], $array['datos_faltantes']);
    }

    /**
     * Armazon con medidas de caja, sin tocar la base de datos.
     */
    private function armazon(
        float $ancho = 52.0,
        float $puente = 18.0,
        float $alto = 40.0,
    ): Armazon {
        $armazon = new Armazon();
        $armazon->codigo = 'AR-PRUEBA';
        $armazon->nombre = 'Armazon de prueba';
        $armazon->ancho_varilla_mm = $ancho;
        $armazon->puente_mm = $puente;
        $armazon->alto_mm = $alto;

        return $armazon;
    }

    /**
     * Lente con diametro de mecanizado, sin tocar la base de datos.
     *
     * El servicio es PURO: no consulta nada. Eso permite probarlo sin
     * levantar PostgreSQL para el calculo, y obliga a que la logica del
     * calculo no dependa de la base.
     */
    private function lente(float $diametro): Lente {
        $lente = new Lente();
        $lente->codigo = 'LE-PRUEBA';
        $lente->nombre = 'Lente de prueba';
        $lente->diametro_corte_mm = $diametro;
        $lente->es_progresivo = false;
        $lente->indice_refraccion = null;
        $lente->material = null;

        return $lente;
    }
}