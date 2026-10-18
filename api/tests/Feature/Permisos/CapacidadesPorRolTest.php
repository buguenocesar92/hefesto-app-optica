<?php

declare(strict_types=1);

namespace Tests\Feature\Permisos;

use App\Enums\RolOptica;
use App\Services\Permisos\CapacidadesDeUsuario;
use Tests\TestCase;

/**
 * Que la graduacion NO la vea todo el mundo.
 *
 * LA REGLA
 * --------
 * La graduacion de una receta es informacion de salud: dice como ve el
 * paciente. Solo el optometrista la lee.
 *
 * Ni el mostrador, que atiende al cliente y ve su ficha. Ni la administracion,
 * que maneja el catalogo y las cuentas. Que una persona maneje la caja no la
 * convierte en alguien que deba leer graduaciones.
 *
 * ESTA PRUEBA ES DE CAPACIDADES, NO DE HTTP
 * -----------------------------------------
 * La suite HTTP (`RutasDeRecetasTest`) comprueba que un 404 sale de verdad.
 * Acoto se comprueba que la capacidad esta bien calculada, que es la regla de
 * negocio que hay detras. Ver docs/07.
 */
class CapacidadesPorRolTest extends TestCase
{
    private CapacidadesDeUsuario $capacidades;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capacidades = new CapacidadesDeUsuario();
    }

    public function test_el_optometrista_ve_las_graduaciones(): void
    {
        $capacidades = $this->capacidades->de($this->usuario(RolOptica::Optometrista));

        $this->assertTrue($capacidades['puede_ver_recetas']);
        $this->assertTrue($capacidades['puede_emitir_recetas']);
        $this->assertTrue($capacidades['puede_registrar_control_calidad']);
    }

    public function test_el_mostrador_no_ve_las_graduaciones_pero_si_atiende(): void
    {
        $capacidades = $this->capacidades->de($this->usuario(RolOptica::Mostrador));

        $this->assertFalse($capacidades['puede_ver_recetas']);
        $this->assertFalse($capacidades['puede_emitir_recetas']);
        $this->assertFalse($capacidades['puede_registrar_control_calidad']);

        // Atender al cliente no es lo mismo que mirar su graduacion.
        $this->assertTrue($capacidades['puede_ver_pacientes']);
        $this->assertTrue($capacidades['puede_gestionar_pedidos']);
        $this->assertTrue($capacidades['puede_registrar_ajustes']);
    }

    public function test_la_administracion_no_ve_las_graduaciones_pero_administra(): void
    {
        $capacidades = $this->capacidades->de($this->usuario(RolOptica::Administrador));

        $this->assertFalse($capacidades['puede_ver_recetas']);
        $this->assertFalse($capacidades['puede_registrar_control_calidad']);

        $this->assertTrue($capacidades['puede_administrar_catalogo']);
        $this->assertTrue($capacidades['puede_administrar_usuarios']);
        $this->assertTrue($capacidades['puede_consultar_bitacora']);

        // Ve pacientes para poder atender el mostrador, pero no su graduacion.
        $this->assertTrue($capacidades['puede_ver_pacientes']);
    }

    public function test_una_cuenta_inactiva_no_tiene_ninguna_capacidad(): void
    {
        $usuario = $this->usuario(RolOptica::Optometrista);
        $usuario->activo = false;

        $capacidades = $this->capacidades->de($usuario);

        foreach ($capacidades as $nombre => $valor) {
            $this->assertFalse($valor, "La capacidad {$nombre} debería estar apagada en una cuenta inactiva.");
        }
    }

    public function test_las_capacidades_de_graduacion_solo_son_del_optometrista(): void
    {
        $this->assertSame(
            [RolOptica::Optometrista],
            RolOptica::conAccesoAGraduaciones(),
            'El rol que lee graduaciones debe seguir siendo uno solo.'
        );

        $this->assertSame(
            [RolOptica::Optometrista],
            RolOptica::queEmitenRecetas(),
        );
    }

    public function test_las_etiquetas_de_los_roles_estan_en_espanol(): void
    {
        $this->assertSame('Mostrador', RolOptica::Mostrador->etiqueta());
        $this->assertSame('Optometrista', RolOptica::Optometrista->etiqueta());
        $this->assertSame('Administracion', RolOptica::Administrador->etiqueta());
    }

    public function test_el_rol_se_resuelve_por_precedencia_y_administracion_va_primero(): void
    {
        $this->assertSame(RolOptica::Administrador, RolOptica::porPrecedencia()[0]);

        // Que administracion vaya primero no le da acceso a graduaciones.
        $this->assertNotContains(
            RolOptica::Administrador,
            RolOptica::conAccesoAGraduaciones()
        );
    }

    private function usuario(RolOptica $rol): \App\Models\Usuario
    {
        $usuario = new \App\Models\Usuario();

        $usuario->rol = $rol;
        $usuario->activo = true;

        return $usuario;
    }
}