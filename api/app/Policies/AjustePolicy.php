<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EstadoAjuste;
use App\Enums\RolOptica;
use App\Models\Ajuste;
use App\Models\Usuario;

/**
 * Quién puede registrar ajustes posteriores a la entrega.
 *
 * TODO LENTE SE AJUSTA
 * --------------------
 * Un armazon que aprieta o que se desliza no es un defecto de fabricacion: es
 * una entrega que no termino. Registrar el ajuste es la parte del producto que
 * sostiene la relacion con el cliente.
 *
 * El ajuste se registra SIN LIMITE DE TIEMPO. Los dias de garantia sugeridos
 * estan en `config('optica.entrega.dias_de_garantia_sugeridos')` y NACEN EN
 * NULL porque son una decision comercial, no un plazo legal verificable. Ver
 * docs/07.
 */
class AjustePolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->activo;
    }

    public function view(Usuario $usuario, Ajuste $ajuste): bool
    {
        return $usuario->activo;
    }

    /**
     * Lo opera el mostrador, no solo el optometrista.
     */
    public function create(Usuario $usuario): bool
    {
        return $usuario->activo
            && in_array($usuario->rol, [RolOptica::Mostrador, RolOptica::Optometrista], true);
    }

    /**
     * Un ajuste ya realizado no se edita: queda como quedo.
     */
    public function update(Usuario $usuario, Ajuste $ajuste): bool
    {
        return $this->create($usuario)
            && $ajuste->estado !== EstadoAjuste::Realizado;
    }

    public function delete(Usuario $usuario, Ajuste $ajuste): bool
    {
        return false;
    }
}