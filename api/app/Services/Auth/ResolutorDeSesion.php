<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\RolOptica;
use App\Models\Optica;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Traduce la identidad de Authentik a una sesion de OpticaHefesto.
 *
 * Tres preguntas, en este orden:
 *
 *  1. De que optica es? Se deduce del equipo de Authentik que declara la
 *     optica (config `authentik.grupo_por_optica`). Sin optica no hay tenant y
 *     no hay datos: se corta el acceso.
 *  2. Que rol tiene? Se deduce de los equipos de rol (config
 *     `authentik.grupos_por_rol`). El orden del mapa manda: gana el primero
 *     que coincide.
 *  3. Existe la cuenta? Si existe, se ACTUALIZA el rol con el que dice
 *     Authentik. Si no existe, se crea.
 *
 * POR QUE EL ROL SE RESUELVE EN CADA INGRESO
 * -------------------------------------------
 * Si a alguien le quitan el equipo de optometristas en Authentik, en esta
 * aplicacion pierde el acceso a las graduaciones en la sesion siguiente. La
 * alternativa (guardar el rol para siempre) deja ventanas de acceso que nadie
 * cerraba, y en una optica esa ventana son recetas de pacientes.
 *
 * POR QUE NO SE CREAN CUENTAS A VOLUNTAD
 * --------------------------------------
 * Sin equipo de optica, no hay ingreso. Y no se crea un administrador: esa
 * cuenta la deja el despliegue (docs/04).
 *
 * NOTA SOBRE LA TABLA `opticas`
 * -----------------------------
 * Aqui se consulta `opticas` CRUDA, y eso es correcto: la tabla no lleva RLS
 * precisamente porque se necesita antes de que exista sesion. Ver docs/03.
 * Lo que se expone al navegador antes de autenticarse es la vista
 * `vw_opticas_publicas`, no esta tabla.
 */
class ResolutorDeSesion
{
    /**
     * @param  array<string, mixed>  $claims
     * @param  list<string>  $grupos  equipos de Authentik.
     */
    public function resolver(array $claims, array $grupos): Usuario
    {
        $optica = $this->resolverOptica($grupos);
        $rol = $this->resolverRol($grupos);

        $correo = $this->correoDe($claims);

        return $this->vincularCuenta($optica, $correo, $rol, $this->nombreDe($claims));
    }

    /**
     * @param  list<string>  $grupos
     */
    private function resolverOptica(array $grupos): Optica
    {
        $equipoOptica = config('authentik.grupo_por_optica');

        if (! is_string($equipoOptica) || $equipoOptica === '') {
            throw new RuntimeException(
                'No hay ningun prefijo de equipo de optica configurado (AUTHENTIK_GRUPO_POR_OPTICA). '
                . 'Sin el no se puede saber de que optica es la persona que entra. Ver docs/05.'
            );
        }

        foreach ($grupos as $grupo) {
            $correlacion = $this->correlacionOptica($grupo);

            if ($correlacion === null) {
                continue;
            }

            $optica = Optica::query()
                ->where('correlacion_identidad', $correlacion)
                ->first();

            if ($optica === null) {
                throw new RuntimeException(
                    'La persona pertenece al equipo de Authentik de una optica que no existe en esta instalacion.'
                );
            }

            if (! $optica->activo) {
                throw new RuntimeException('La optica esta desactivada.');
            }

            return $optica;
        }

        throw new RuntimeException('La cuenta no pertenece a ninguna optica habilitada.');
    }

    /**
     * La correlacion viaja en el nombre del equipo de Authentik, con el
     * formato `<prefijo>:<correlacion>`, por ejemplo `OpticaHefesto:centro`.
     * Ver docs/04.
     */
    private function correlacionOptica(string $grupo): ?string
    {
        $prefijo = (string) config('authentik.grupo_por_optica', 'OpticaHefesto');
        $prefijo = str_ends_with($prefijo, ':') ? $prefijo : $prefijo . ':';

        if (! str_starts_with($grupo, $prefijo)) {
            return null;
        }

        $correlacion = trim(substr($grupo, strlen($prefijo)));

        return $correlacion === '' ? null : $correlacion;
    }

    /**
     * @param  list<string>  $grupos
     */
    private function resolverRol(array $grupos): RolOptica
    {
        $mapa = config('authentik.grupos_por_rol', []);

        if (! is_array($mapa) || $mapa === []) {
            throw new RuntimeException(
                'No hay ningun equipo de rol configurado en config/authentik.php. '
                . 'Sin el, cualquier persona que entre tendria el mismo rol. Ver docs/05.'
            );
        }

        $normalizados = array_map(
            static fn (string $grupo): string => mb_strtolower(trim($grupo)),
            $grupos
        );

        foreach ($mapa as $equipo => $rol) {
            if (in_array(mb_strtolower(trim((string) $equipo)), $normalizados, true)) {
                $caso = RolOptica::tryFrom((string) $rol);

                if ($caso === null) {
                    throw new RuntimeException('El rol configurado "' . $rol . '" no es un rol valido.');
                }

                return $caso;
            }
        }

        throw new RuntimeException('La persona no pertenece a ningun equipo de rol de OpticaHefesto.');
    }

    /**
     * El correo es la llave de enlace entre Authentik y la tabla `usuarios`.
     *
     * @param  array<string, mixed>  $claims
     */
    private function correoDe(array $claims): string
    {
        $correo = $claims['correo'] ?? null;

        if (is_string($correo) && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false) {
            return mb_strtolower($correo);
        }

        throw new RuntimeException(
            'El token de identificacion no trae un correo valido. '
            . 'Sin correo no hay forma de vincular la cuenta con la tabla usuarios.'
        );
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function nombreDe(array $claims): ?string
    {
        $nombre = $claims['nombre'] ?? null;

        if (is_string($nombre) && trim($nombre) !== '') {
            return trim($nombre);
        }

        $preferido = $claims['usuario_preferido'] ?? null;

        return is_string($preferido) && trim($preferido) !== '' ? trim($preferido) : null;
    }

    private function vincularCuenta(Optica $optica, string $correo, RolOptica $rol, ?string $nombre): Usuario
    {
        return DB::transaction(function () use ($optica, $correo, $rol, $nombre): Usuario {
            $usuario = Usuario::query()
                ->where('tenant_id', $optica->id)
                ->where('correo', $correo)
                ->first();

            if ($usuario === null) {
                return Usuario::query()->create([
                    'tenant_id' => $optica->id,
                    'nombre' => $nombre ?: $correo,
                    'correo' => $correo,
                    'rol' => $rol,
                    'activo' => true,
                    'ultimo_ingreso_en' => now(),
                ]);
            }

            /*
            | El rol se actualiza en cada ingreso; el nombre solo si Authentik
            | lo trae, para no borrar un nombre que la optica corrigio.
            */
            $usuario->rol = $rol;

            if ($nombre !== null && $nombre !== '') {
                $usuario->nombre = $nombre;
            }

            $usuario->ultimo_ingreso_en = now();

            $usuario->save();

            return $usuario;
        });
    }
}