<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EstadoReceta;
use App\Enums\RolOptica;
use App\Models\Receta;
use App\Models\Usuario;

/**
 * Quién puede leer y emitir recetas.
 *
 * ESTA ES LA POLICY MAS ESTRICTA DE LA APLICACION
 * ----------------------------------------------
 * Una receta optica dice como ve el paciente: es informacion de salud. Solo el
 * optometrista la lee. El mostrador, que atiende al cliente y ve su ficha, NO
 * la ve. Y el administrador, que maneja el catalogo y las cuentas, tampoco.
 *
 * El cargo de una persona no vuelve menos sensible el dato.
 *
 * UN RECHAZO RESPONDE 404, NO 403
 * ------------------------------
 * Un 403 le confirmaria a quien pregunta que el paciente existe. Por eso
 * bootstrap/app.php convierte la excepcion de autorizacion en 404. Ver docs/07.
 */
class RecetaPolicy
{
    /**
     * Listar recetas de la optica.
     */
    public function viewAny(Usuario $usuario): bool
    {
        return $this->esOptometrista($usuario);
    }

    public function view(Usuario $usuario, Receta $receta): bool
    {
        return $this->esOptometrista($usuario);
    }

    /**
     * Emitir una receta nueva.
     */
    public function create(Usuario $usuario): bool
    {
        return $this->esOptometrista($usuario);
    }

    /**
     * Corregir una receta.
     *
     * Solo mientras este emitida: una receta anulada o reemplazada es un
     * documento cerrado, y corregirlaeria romper el registro. Para cambios de
     * verdad se emite otra receta y se marca esta como reemplazada.
     */
    public function update(Usuario $usuario, Receta $receta): bool
    {
        return $this->esOptometrista($usuario)
            && $receta->estado === EstadoReceta::Emitida;
    }

    /**
     * Anular una receta.
     *
     * Anular no es borrar: deja constancia de que existio. Por eso se permite
     * y por eso el estado `anulada` es parte del modelo.
     */
    public function anular(Usuario $usuario, Receta $receta): bool
    {
        return $this->esOptometrista($usuario)
            && $receta->estado === EstadoReceta::Emitida;
    }

    /**
     * Las recetas no se borran nunca.
     *
     * Forman parte de la historia de salud del paciente y de un documento
     * comercial. Se anulan.
     */
    public function delete(Usuario $usuario, Receta $receta): bool
    {
        return false;
    }

    /**
     * Si el rol puede leer graduaciones.
     */
    public function esOptometrista(Usuario $usuario): bool
    {
        return $usuario->activo
            && in_array($usuario->rol, RolOptica::conAccesoAGraduaciones(), true);
    }
}