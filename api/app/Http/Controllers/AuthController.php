<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Optica;
use App\Services\Auth\ProveedorOidcAuthentik;
use App\Services\Auth\ResolutorDeSesion;
use App\Services\Auth\VerificadorIdToken;
use App\Services\Permisos\CapacidadesDeUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Throwable;

/**
 * Ingreso, salida y estado de la sesion.
 *
 * EL FLUJO
 * --------
 *  1. `GET /api/v1/acceso/entrar` arma la URL del proveedor y guarda un
 *     estado aleatorio en la sesion (proteccion CSRF del flujo OIDC).
 *  2. El proveedor devuelve a `GET /api/v1/acceso/callback` con un codigo.
 *  3. Se canjea el codigo por tokens y se VERIFICA la firma del token de
 *     identificacion.
 *  4. De los equipos de Authentik se deduce la optica y el rol, y se abre la
 *     sesion de Laravel. Ver App\Services\Auth\ResolutorDeSesion.
 *
 * SIN EQUIPO DE OPTICA NO HAY INGRESO
 * -----------------------------------
 * Y sin equipo de rol tampoco. El mapa de config/authentik.php arranca vacio a
 * proposito: mientras no se llene, nadie entra. Ver docs/04.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly VerificadorIdToken $verificador,
        private readonly ProveedorOidcAuthentik $proveedor,
        private readonly ResolutorDeSesion $resolutor,
        private readonly CapacidadesDeUsuario $capacidades,
    ) {}

    /**
     * Primer paso: mandar al proveedor de identidad.
     */
    public function entrar(Request $peticion): JsonResponse
    {
        $autorizacion = $this->proveedor->urlDeAutorizacion();

        Session::put('optica_estado_oidc', $autorizacion['estado']);

        return response()->json([
            'url' => $autorizacion['url'],
        ]);
    }

    /**
     * Segundo paso: canjear el codigo y abrir la sesion.
     */
    public function callback(Request $peticion): JsonResponse
    {
        $estadoEsperado = Session::pull('optica_estado_oidc');

        $estadoRecibido = $peticion->query('state');

        if (! is_string($estadoEsperado) || ! is_string($estadoRecibido)
            || ! hash_equals($estadoEsperado, $estadoRecibido)) {
            return response()->json([
                'mensaje' => 'La respuesta del proveedor de identidad no corresponde a esta peticion.',
            ], 400);
        }

        $codigo = $peticion->query('code');

        if (! is_string($codigo) || $codigo === '') {
            return response()->json([
                'mensaje' => 'El proveedor de identidad no devolvio un codigo de autorizacion.',
            ], 400);
        }

        try {
            $tokens = $this->proveedor->canjearCodigo($codigo);

            $claims = $this->verificador->verificar($tokens['token_id']);

            $grupos = $this->gruposDe($claims);

            $usuario = $this->resolutor->resolver($claims, $grupos);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'mensaje' => 'No se pudo completar el ingreso. Revise los registros del servidor.',
            ], 403);
        }

        Auth::guard('web')->login($usuario);

        $peticion->session()->regenerate();

        // No se devuelve el usuario aqui a proposito: la interfaz pide
        // `GET /api/v1/yo` recien ingressada y asi hay un solo lugar del que
        // sale la informacion de sesion.
        return response()->json(['mensaje' => 'Ingreso correcto.']);
    }

    public function salir(): JsonResponse
    {
        $this->proveedor->cerrarSesion();

        Session::invalidate();
        Session::regenerateToken();

        return response()->json(['mensaje' => 'Sesion cerrada.']);
    }

    /**
     * Estado de la sesion, y lo unico que la interfaz necesita saber de la
     * persona. Las capacidades vienen de la API; la interfaz las usa para
     * esconder botones, no para proteger datos.
     */
    public function yo(Request $peticion): JsonResponse
    {
        $usuario = $peticion->user();

        if ($usuario === null) {
            return response()->json(['usuario' => null]);
        }

        /** @var Optica $optica */
        $optica = $usuario->optica;

        return response()->json([
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'correo' => $usuario->correo,
                'rol' => $usuario->rol->value,
                'rol_etiqueta' => $usuario->rol->etiqueta(),
                'optica' => [
                    'id' => $optica->id,
                    'nombre' => $optica->nombre,
                    'zona_horaria' => $optica->zona_horaria,
                ],
                'capacidades' => $this->capacidades->de($usuario),
            ],
        ]);
    }

    /**
     * Los equipos de la persona, del claim `groups` o del que el proveedor
     * use por omision.
     *
     * @param  array<string, mixed>  $claims
     * @return list<string>
     */
    private function gruposDe(array $claims): array
    {
        $grupos = $claims['groups'] ?? $claims['grupos'] ?? [];

        if (! is_array($grupos)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $grupo): string => (string) $grupo,
            $grupos
        ));
    }
}