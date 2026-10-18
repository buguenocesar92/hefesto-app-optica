<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use RuntimeException;

/**
 * Verifica el token de identificacion que entrega Authentik.
 *
 * POR QUE NO SE USA EL ALGORITMO DEL TOKEN
 * ---------------------------------------
 * Si el verificador leyera el algoritmo de la cabecera del token y usara ese,
 * un atacante podria cambiarlo a HS256 y firmar el token con la clave PUBLICA
 * del proveedor, que es publica de verdad. Ahi el ataque se llama confusion de
 * algoritmo.
 *
 * Por eso el algoritmo se comprueba ANTES de verificar nada, contra el valor
 * de `config('authentik.algoritmo')`, y si no coincide se rechaza.
 *
 * QUE SE COMPRUEBA
 * ----------------
 *   - la firma, con las claves del JWKS del proveedor;
 *   - el algoritmo, contra la configuracion;
 *   - la audiencia (`aud`), que tiene que ser el cliente propio de esta
 *     aplicacion;
 *   - el emisor (`iss`), que tiene que ser el proveedor configurado;
 *   - `exp`, `iat` y `nbf`, con el margen de reloj de
 *     `config('authentik.leeway')`.
 */
class VerificadorIdToken
{
    public function __construct(private readonly DescubridorOidc $descubridor) {}

    /**
     * @return array<string, mixed>  Los claims del token ya verificados.
     */
    public function verificar(string $token): array
    {
        $algoritmo = (string) config('authentik.algoritmo', 'RS256');

        // Se lee la cabecera sin verificar para mirar SOLO el algoritmo.
        $partes = explode('.', $token);

        if (count($partes) !== 3) {
            throw new RuntimeException('El token de identificacion no tiene la forma de un JWT.');
        }

        $cabecera = json_decode(
            (string) base64_decode(strtr($partes[0], '-_', '+/'), true),
            true
        );

        $algoritmoDelToken = is_array($cabecera) ? ($cabecera['alg'] ?? null) : null;

        if (! is_string($algoritmoDelToken) || $algoritmoDelToken !== $algoritmo) {
            throw new RuntimeException(
                'El token de identificacion declara un algoritmo distinto del configurado. Se rechaza.'
            );
        }

        $claves = JWK::parseKeySet(['keys' => $this->descubridor->jwks()], $algoritmo);

        $claims = (array) JWT::decode($token, $claves);

        $this->verificarEmisor($claims);
        $this->verificarAudiencia($claims);

        return $claims;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function verificarEmisor(array $claims): void
    {
        $esperado = rtrim((string) config('authentik.issuer', ''), '/');
        $recibido = isset($claims['iss']) ? rtrim((string) $claims['iss'], '/') : '';

        if ($esperado === '' || $recibido !== $esperado) {
            throw new RuntimeException('El token de identificacion no viene del proveedor de identidad configurado.');
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function verificarAudiencia(array $claims): void
    {
        $esperado = (string) config('authentik.client_id', '');

        if ($esperado === '') {
            throw new RuntimeException('No hay AUTHENTIK_CLIENT_ID configurado. Ver docs/05.');
        }

        $audiencia = $claims['aud'] ?? null;

        $coincide = is_string($audiencia)
            ? hash_equals($esperado, $audiencia)
            : is_array($audiencia) && in_array($esperado, $audiencia, true);

        if (! $coincide) {
            throw new RuntimeException(
                'El token de identificacion no fue emitido para esta aplicacion. '
                .'Suele significar que se copio el cliente de otra aplicacion de Hefesto.'
            );
        }
    }
}