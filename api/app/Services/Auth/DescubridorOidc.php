<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Descubre la configuracion de Authentik por OpenID Connect.
 *
 * DESCUBRIMIENTO, NO RUTAS FIJAS
 * -----------------------------
 * A partir de `issuer` se lee `.well-known/openid-configuration`, y de ahi salen
 * las URLs de autorizacion, de token y de revocacion. No hay ninguna ruta
 * escrita a mano: si Authentik cambia sus endpoints, esta clase se entera sola.
 *
 * POR QUE ESTO NO ES UN "IR Y VER"
 * --------------------------------
 * Un descubridor que devuelve nulo cuando algo falla es un descubridor que deja
 * entrar a gente sin avisar. Aquí, si la consulta falla, se LANZA. La
 * diferencia es que un ingreso fallido es un ingreso que no ocurrió.
 */
class DescubridorOidc
{
    public function metadatos(): array
    {
        $issuer = (string) config('authentik.issuer', '');

        if ($issuer === '') {
            throw new RuntimeException(
                'No hay AUTHENTIK_ISSUER configurado. Sin el no se puede descubrir el proveedor de identidad. Ver docs/05.'
            );
        }

        $url = rtrim($issuer, '/') . '/.well-known/openid-configuration';

        $clave = 'optica:oidc:metadatos';

        $cacheado = Cache::remember($clave, (int) config('authentik.cache_metadata', 3600), function () use ($url): array {
            $respuesta = Http::timeout(10)->acceptJson()->get($url);

            if (! $respuesta->successful()) {
                throw new RuntimeException(
                    'No se pudo leer la configuracion de OpenID Connect del proveedor de identidad ('
                    .$url.', HTTP '.$respuesta->status().').'
                );
            }

            return (array) $respuesta->json();
        });

        return $cacheado;
    }

    /**
     * Las claves publicas del proveedor, en formato JWKS.
     *
     * Se cachean mas rato que los metadatos: las claves rotan pocas veces y el
     * JWKS es lo que se usa para verificar la firma de cada ingreso.
     *
     * @return list<array<string, mixed>>
     */
    public function jwks(): array
    {
        $jwksUri = $this->metadatos()['jwks_uri'] ?? null;

        if (! is_string($jwksUri) || $jwksUri === '') {
            throw new RuntimeException('La configuracion del proveedor de identidad no declara jwks_uri.');
        }

        $cacheado = Cache::remember(
            'optica:oidc:jwks',
            (int) config('authentik.cache_jwks', 86400),
            function () use ($jwksUri): array {
                $respuesta = Http::timeout(10)->acceptJson()->get($jwksUri);

                if (! $respuesta->successful()) {
                    throw new RuntimeException(
                        'No se pudieron obtener las claves publicas del proveedor de identidad ('
                        .$jwksUri.', HTTP '.$respuesta->status().').'
                    );
                }

                return (array) $respuesta->json();
            }
        );

        $claves = $cacheado['keys'] ?? [];

        return is_array($claves) ? array_values($claves) : [];
    }
}