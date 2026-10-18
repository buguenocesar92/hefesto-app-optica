<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Usuario;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Proveedor de autenticacion que arma una sesion de Laravel a partir de un
 * token de Authentik.
 *
 * EL USUARIO SE BUSCA CON TENIENDO EN CUENTA LA OPTICA
 * -----------------------------------------------------
 * `usuarios` tiene indice unico en `(tenant_id, correo)`, no en el correo solo:
 * una misma persona puede tener cuentas en dos opticas. Por eso este
 * proveedor exige que le pasen la optica, y busca siempre dentro de ella.
 *
 * SI LA CUENTA ESTA DESACTIVADA
 * ----------------------------
 * El ingreso falla, aunque el rol en Authentik siga siendo el correcto. La
 * cuenta se desactiva cuando la persona deja de trabajar en la optica, y
 * desactiva el rol desde Authentik tomaria hasta el siguiente ingreso de esa
 * persona.
 */
class ProveedorOidcAuthentik
{
    /**
     * Devuelve el usuario si el token es valido, o null si no corresponde a
     * ninguna cuenta activa de esta optica.
     *
     * @param  array<string, mixed>  $claims
     */
    public function usuario(array $claims, string $opticaId): ?Authenticatable
    {
        $correo = $claims['correo'] ?? $claims['email'] ?? null;

        if (! is_string($correo) || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $usuario = Usuario::query()
            ->where('tenant_id', $opticaId)
            ->where('correo', mb_strtolower($correo))
            ->where('activo', true)
            ->first();

        return $usuario;
    }

    /**
     * Inicia el flujo de autorizacion: arma la URL a la que va el navegador.
     *
     * @return array{url: string, estado: string}
     */
    public function urlDeAutorizacion(): array
    {
        $issuer = rtrim((string) config('authentik.issuer', ''), '/');

        if ($issuer === '') {
            throw new RuntimeException('No hay AUTHENTIK_ISSUER configurado. Ver docs/05.');
        }

        $metadatos = app(DescubridorOidc::class)->metadatos();

        $autorizacion = $metadatos['authorization_endpoint'] ?? null;

        if (! is_string($autorizacion) || $autorizacion === '') {
            throw new RuntimeException('El proveedor de identidad no declara authorization_endpoint.');
        }

        // El estado es un valor aleatorio que viaja de vuelta para comprobar
        // que la respuesta corresponde a esta peticion y no a otra. Es la
        // proteccion contra CSRF del flujo OIDC.
        $estado = bin2hex(random_bytes(16));

        $query = http_build_query([
            'client_id' => (string) config('authentik.client_id'),
            'response_type' => 'code',
            'redirect_uri' => (string) config('authentik.redirect_uri'),
            'scope' => implode(' ', (array) config('authentik.scopes', [])),
            'state' => $estado,
        ]);

        return [
            'url' => $autorizacion.'?'.$query,
            'estado' => $estado,
        ];
    }

    /**
     * Canjea el codigo de autorizacion por tokens.
     *
     * @return array{token_id: string, token_acceso: ?string}
     */
    public function canjearCodigo(string $codigo): array
    {
        $metadatos = app(DescubridorOidc::class)->metadatos();

        $token = $metadatos['token_endpoint'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('El proveedor de identidad no declara token_endpoint.');
        }

        $respuesta = Http::asForm()
            ->timeout(15)
            ->post($token, [
                'grant_type' => 'authorization_code',
                'code' => $codigo,
                'redirect_uri' => (string) config('authentik.redirect_uri'),
                'client_id' => (string) config('authentik.client_id'),
                'client_secret' => (string) config('authentik.client_secret'),
            ]);

        if (! $respuesta->successful()) {
            throw new RuntimeException(
                'El proveedor de identidad no acepto el codigo de autorizacion (HTTP '
                .$respuesta->status().').'
            );
        }

        $cuerpo = (array) $respuesta->json();

        $tokenId = $cuerpo['id_token'] ?? null;

        if (! is_string($tokenId) || $tokenId === '') {
            throw new RuntimeException('El proveedor de identidad no devolvio un token de identificacion.');
        }

        return [
            'token_id' => $tokenId,
            'token_acceso' => isset($cuerpo['access_token']) && is_string($cuerpo['access_token'])
                ? $cuerpo['access_token']
                : null,
        ];
    }

    /**
     * Cierra la sesion de Laravel.
     */
    public function cerrarSesion(): void
    {
        Auth::guard('web')->logout();
    }
}