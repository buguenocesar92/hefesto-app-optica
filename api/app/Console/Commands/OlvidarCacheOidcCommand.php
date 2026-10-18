<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Olvida la metadata y las claves del proveedor de identidad.
 *
 * CUANDO SE CORRE
 * ---------------
 *  - despues de cambiar `AUTHENTIK_ISSUER`, `AUTHENTIK_CLIENT_ID` o el
 *    secreto;
 *  - despues de cambiar de las claves del proveedor;
 *  - cuando el ingreso falla con "el token no viene del proveedor
 *    configurado" y se sospecha que hay metadata vieja.
 *
 * En el resto de los casos no hace falta: la metadata dura una hora y el JWKS
 * un dia (ver config/authentik.php).
 */
class OlvidarCacheOidcCommand extends Command
{
    protected $signature = 'optica:olvidar-cache-oidc';

    protected $description = 'Olvida la metadata y las claves publicas del proveedor de identidad.';

    public function handle(): int
    {
        Cache::forget('optica:oidc:metadatos');
        Cache::forget('optica:oidc:jwks');

        $this->components->info('Cache de OpenID Connect olvidada.');

        return self::SUCCESS;
    }
}