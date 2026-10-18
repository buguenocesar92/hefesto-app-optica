<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RolOptica;
use App\Models\Optica;
use App\Models\Usuario;
use Illuminate\Console\Command;

/**
 * Crea la primera cuenta de administracion de una optica.
 *
 * POR QUE ESTE COMANDO Y NO UN SEEDER
 * -----------------------------------
 * El despliegue deja una sola cuenta, con la que se entra por primera vez.
 * Toda cuenta posterior se crea desde la interfaz, y su ROL lo decide
 * Authentik: aca se puede dar de alta a alguien, pero con que rol se metera
 * depende de los equipos a los que pertenezca (ver
 * App\Services\Auth\ResolutorDeSesion).
 *
 * ESTA CUENTA ES LA QUE SE DESACTIVA SI SE OLVIDA ENTRAR
 * ------------------------------------------------------
 * Ver docs/04 y docs/07.
 */
class CrearAdministradorCommand extends Command
{
    protected $signature = 'optica:crear-administrador
        {--optica= : Correlacion de la optica. Si no se indica, se usa la primera activa.}
        {--nombre= : Nombre de la persona.}
        {--correo= : Correo de la persona. Debe coincidir con el de Authentik.}';

    protected $description = 'Crea la primera cuenta de administracion de una optica.';

    public function handle(): int
    {
        $optica = $this->resolverOptica();

        if ($optica === null) {
            return self::FAILURE;
        }

        $correo = $this->opcion('correo');

        if (! is_string($correo) || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error('Falta --correo, o el correo no es valido.');

            return self::FAILURE;
        }

        $nombre = $this->opcion('nombre');

        $existente = Usuario::query()
            ->where('tenant_id', $optica->id)
            ->where('correo', mb_strtolower($correo))
            ->first();

        if ($existente !== null) {
            $this->components->error('Ya existe una cuenta con ese correo en esta optica.');

            return self::FAILURE;
        }

        /*
        | La cuenta se crea con `activo` en true y con rol administracion.
        |
        | El rol va a ser SOBREESCRITO en el primer ingreso por lo que diga
        | Authentik. Es intencional: el rol de verdad no lo decide este comando
        | ni esta base, lo decide quien administra la identidad.
        */
        $usuario = Usuario::query()->create([
            'tenant_id' => $optica->id,
            'nombre' => is_string($nombre) && $nombre !== '' ? $nombre : 'Administracion',
            'correo' => mb_strtolower($correo),
            'rol' => RolOptica::Administrador,
            'activo' => true,
        ]);

        $this->components->info('Cuenta creada: '.$usuario->correo);
        $this->components->warn(
            'Recuerde: para que la persona pueda entrar, en Authentik tiene que pertenecer al equipo '
            .RolOptica::Administrador->equipoAuthentik().' y al equipo '
            .config('authentik.grupo_por_optica').':'.$optica->correlacion_identidad.'.'
        );

        return self::SUCCESS;
    }

    private function resolverOptica(): ?Optica
    {
        $correlacion = $this->option('optica');

        if (is_string($correlacion) && $correlacion !== '') {
            return Optica::query()
                ->where('correlacion_identidad', $correlacion)
                ->first();
        }

        $optica = Optica::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->first();

        if ($optica === null) {
            $this->components->error('No hay ninguna optica activa. Revise la tabla opticas.');
        }

        return $optica;
    }
}