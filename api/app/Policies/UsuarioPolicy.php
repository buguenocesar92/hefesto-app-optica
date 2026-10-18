<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RolOptica;
use App\Models\Usuario;

/**
 * Quién puede administrar las cuentas de la optica.
 *
 * Solo administracion. Y con dos limites que estan en el modelo y no en la
 * policy:
 *
 *   - el ROL se resuelve desde Authentik en cada ingreso, asi que esta
 *     aplicacion puede cambiar el rol de alguien pero no inventarlo
 *     (ver App\Services\Auth\ResolutorDeSesion);
 *   - no hay contrasenas aca (ver docs/07).
 *
 * Lo que si hace esta aplicacion es DESACTIVAR una cuenta. La persona deja de
 * entrar de inmediato, sin depender de que alguien le quite el equipo en
 * Authentik.
 */
class UsuarioPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $this->esAdministrador($usuario);
    }

    public function view(Usuario $usuario, Usuario $objetivo): bool
    {
        return $this->esAdministrador($usuario);
    }

    public function create(Usuario $usuario): bool
    {
        return $this->esAdministrador($usuario);
    }

    public function update(Usuario $usuario, Usuario $objetivo): bool
    {
        return $this->esAdministrador($usuario);
    }

    /**
     * Desactivar una cuenta.
     */
    public function desactivar(Usuario $usuario, Usuario $objetivo): bool
    {
        return $this->esAdministrador($usuario);
    }

    /**
     * Las cuentas no se borran.
     *
     * Un usuario aparece en recetas emitidas, en revisiones de control de
     * calidad y en la bitacora. Borrarlo dejaria huecos en documentos y en el
     * registro de accesos. Se desactiva.
     */
    public function delete(Usuario $usuario, Usuario $objetivo): bool
    {
        return false;
    }

    /**
     * Nadie puede desactivarse a si mismo.
     *
     * Sin esta regla, un administrador puede dejar la optica sin ninguna
     * cuenta valida y nadie mas entra a arreglarlo.
     */
    public function desactivarSelf(Usuario $usuario, Usuario $objetivo): bool
    {
        return $this->esAdministrador($usuario) && $usuario->id !== $objetivo->id;
    }

    private function esAdministrador(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->rol === RolOptica::Administrador;
    }
}