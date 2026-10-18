<?php

declare(strict_types=1);

namespace App\Services\Permisos;

use App\Enums\RolOptica;
use App\Models\Usuario;

/**
 * Capacidades de una persona dentro de la optica.
 *
 * POR QUE EXISTE Y POR QUE NO BASTA EL ROL
 * ---------------------------------------
 * El rol es la SEMILLA del permiso. Las capacidades son el permiso. La
 * diferencia importa porque no todos los roles hacen lo mismo con los mismos
 * datos: el mostrador atiende al cliente y ve el catalogo, pero no lee
 * graduaciones; el administrador maneja el catalogo y los usuarios, pero tampoco
 * lee graduaciones.
 *
 * QUE DECIDE LA CAPACIDAD
 * -----------------------
 * Que botones muestra la interfaz y, mas importante, que devuelve la API.
 * La interfaz esconde botones por cortesia: la seguridad esta en la API. Ver
 * docs/07.
 *
 * LA GRADUACION ES EL DATO DIFERENTE
 * ----------------------------------
 * Es informacion de salud: dice como ve el paciente. Que una persona maneje el
 * catalogo o la caja no la convierte en alguien que deba leer graduaciones. Por
 * eso `puede_ver_recetas` es unica del optometrista, y por eso
 * `administrador` NO lo tiene.
 */
class CapacidadesDeUsuario
{
    /**
     * @return array<string, bool>
     */
    public function de(Usuario $usuario): array
    {
        $rol = $usuario->rol instanceof RolOptica
            ? $usuario->rol
            : RolOptica::from($usuario->rol);

        $todos = [RolOptica::Mostrador, RolOptica::Optometrista, RolOptica::Administrador];
        $optico = [RolOptica::Mostrador, RolOptica::Optometrista];

        return [
            // Ficha del paciente: la ve todo el mundo que trabaja en el local.
            'puede_ver_pacientes' => $this->tiene($rol, $todos),

            // La graduacion no. Unicamente el optometrista.
            'puede_ver_recetas' => $this->tiene($rol, RolOptica::conAccesoAGraduaciones()),
            'puede_emitir_recetas' => $this->tiene($rol, RolOptica::queEmitenRecetas()),
            'puede_registrar_control_calidad' => $this->tiene($rol, RolOptica::conAccesoAGraduaciones()),

            // El mostrador envia pedidos y entrega los lentes al mostrador.
            'puede_gestionar_pedidos' => $this->tiene($rol, $optico),
            'puede_registrar_ajustes' => $this->tiene($rol, $optico),

            // La administracion maneja el catalogo y las cuentas, no las recetas.
            'puede_administrar_catalogo' => $this->tiene($rol, [RolOptica::Administrador]),
            'puede_administrar_usuarios' => $this->tiene($rol, [RolOptica::Administrador]),
            'puede_consultar_bitacora' => $this->tiene($rol, [RolOptica::Administrador]),
        ];
    }

    /**
     * Si el rol esta en la lista de roles que tienen la capacidad.
     *
     * @param  array<int, RolOptica>  $roles
     */
    private function tiene(RolOptica $rol, array $roles): bool
    {
        return in_array($rol, $roles, true);
    }
}