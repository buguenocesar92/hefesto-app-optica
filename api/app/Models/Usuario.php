<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RolOptica;
use App\Models\Concerns\PerteneceAOptica;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Persona que opera la optica.
 *
 * NO HAY CONTRASENA
 * -----------------
 * No existe columna de contrasena y no se debe agregar. La credencial vive en
 * Authentik; aca solo se guarda quien es la persona. Ver
 * App\Services\Auth. Ver docs/07.
 *
 * `correo` NO es unico en toda la base: el indice unico es
 * `(tenant_id, correo)`. Una misma persona puede tener cuentas en dos opticas.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $nombre
 * @property string $correo
 * @property RolOptica $rol
 * @property bool $activo
 * @property \Illuminate\Support\Carbon|null $ultimo_ingreso_en
 */
class Usuario extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UsuarioFactory> */
    use HasFactory;
    use Notifiable;
    use PerteneceAOptica;
    use HasUuids;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre',
        'correo',
        'rol',
        'activo',
        'ultimo_ingreso_en',
    ];

    protected function casts(): array
    {
        return [
            'rol' => RolOptica::class,
            'activo' => 'boolean',
            'ultimo_ingreso_en' => 'datetime',
        ];
    }

    /*
    | En cuanto a la contrasena
    | -------------------------
    | `Illuminate\Foundation\Auth\User` implementa los metodos de contrasena
    | de la interfaz `Authenticatable` heredando un valor por omision, y aca
    | no se sobreescriben. La razon es que el sistema NUNCA autentica por
    | contrasena: la sesion se abre con `Auth::login()` despues de validar un
    | token de Authentik (ver App\Services\Auth). Si alguna vez se tocara esa
    | parte, la columna no existe y la consulta fallaria de forma visible, que
    | es justo lo que se busca. Ver docs/07.
    */
}