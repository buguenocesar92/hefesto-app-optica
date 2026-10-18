<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Administracion\BitacoraController;
use App\Http\Controllers\Administracion\UsuarioController;
use App\Http\Controllers\Catalogo\ArmazonController;
use App\Http\Controllers\Catalogo\LenteController;
use App\Http\Controllers\Entregas\AjusteController;
use App\Http\Controllers\Entregas\EntregaController;
use App\Http\Controllers\Pacientes\PacienteController;
use App\Http\Controllers\Pedidos\ControlCalidadController;
use App\Http\Controllers\Pedidos\PedidoLaboratorioController;
use App\Http\Controllers\Recetas\RecetaController;
use App\Http\Controllers\Recetas\VencimientoController;
use App\Models\Optica;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la API de OpticaHefesto
|--------------------------------------------------------------------------
|
| Todas cuelgan del prefijo `/api/v1`. La interfaz (Nuxt) es el unico cliente.
|
| COMO SE DIVIDE LA AUTORIZACION
| ------------------------------
| En los controladores, con policies de Laravel. Para la graduacion hay una
| capa mas: el middleware `receta.auditada`, que ademas deja el intento en la
| bitacora. Ver docs/07.
|
| UN RECHAZO ES 404, NO 403
| -------------------------
| Un 403 le confirmaria a quien pregunta que el recurso existe. bootstrap/app.php
| traduce la excepcion de autorizacion a 404 en todas las rutas de la API.
|
| EL SUFIJO EN EL NOMBRE DE LAS RUTAS
| ----------------------------------
| Las rutas de receta y graduacion terminan en `.auditada`, y el middleware usa
| ese nombre para saber que recurso se esta abriendo. Si alguien copia una ruta
| de esas sin el sufijo, deja de auditarse: es el recordatorio de que el
| middleware no es solo decorativo.
|
*/

| Comprobacion de salud. NO toca la base de datos a proposito: si tocara una
 | tabla protegida, fallaria por RLS y el contenedor se reiniciaria en bucle
 | cuando todavia este levantando la base. Ver docs/04.
 */
Route::get('/v1/salud', fn () => response()->json([
    'ok' => true,
    'servicio' => 'optica-hefesto',
]))->name('salud');

/*
| Ingreso. Estas tres rutas son las unicas que corren SIN sesion, y por eso
| son las unicas que pueden tocar `opticas` (que no tiene RLS).
|
| `entrar` es POST y no GET: se pide desde el navegador con un cuerpo, y un GET
| con cuerpo es un error de metodo que no dice nada. Ver web/server/api.
*/
Route::prefix('/v1/acceso')->name('acceso.')->group(function (): void {
    /*
    | El selector de ingreso.
    |
    | ESTA RUTA ES LA QUE USA LA VISTA. Llama a `Optica::publicas()`, que lee
    | `vw_opticas_publicas`: tres columnas y solo opticas activas. No lee la
    | tabla cruda, y en particular nunca expone `correlacion_identidad`, que
    | es la llave que liga a una persona con una optica en Authentik.
    |
    | Ver docs/03.
    */
    Route::get('/opticas', fn () => response()->json([
        'opticas' => Optica::publicas(),
    ]))->name('opticas');

    Route::post('/entrar', [AuthController::class, 'entrar'])->name('entrar');
    Route::get('/callback', [AuthController::class, 'callback'])->name('callback');
    Route::post('/salir', [AuthController::class, 'salir'])->name('salir');
});

/*
| Rutas que exigen sesion.
*/
Route::middleware('auth')->group(function (): void {

    Route::get('/v1/yo', [AuthController::class, 'yo'])->name('yo');

    /* ---------------------------------------------------------------------
    | Pacientes
    | ------------------------------------------------------------------ */

    Route::prefix('/v1/pacientes')->name('pacientes.')->group(function (): void {
        Route::get('/', [PacienteController::class, 'index'])->name('index');
        Route::post('/', [PacienteController::class, 'store'])->name('store');
        Route::get('/{paciente}', [PacienteController::class, 'show'])->name('show');
        Route::put('/{paciente}', [PacienteController::class, 'update'])->name('update');
        Route::post('/{paciente}/desactivar', [PacienteController::class, 'desactivar'])->name('desactivar');
    });

    /* ---------------------------------------------------------------------
    | Recetas y graduaciones. Con bitacora de accesos.
    | ------------------------------------------------------------------ */

    Route::prefix('/v1/recetas')->name('recetas.')->group(function (): void {
        Route::get('/', [RecetaController::class, 'index'])->name('index');
        Route::post('/', [RecetaController::class, 'store'])->name('store');
        Route::get('/{receta}', [RecetaController::class, 'show'])->name('show.auditada');
        Route::post('/{receta}/anular', [RecetaController::class, 'anular'])->name('anular.auditada');

        /*
        | Las graduaciones van por debajo de la receta porque el recurso de
        | la ruta sigue siendo la receta: es la que autoriza y la que se
        | registra en la bitacora.
        */
        Route::get('/{receta}/graduaciones', [RecetaController::class, 'graduaciones'])
            ->name('graduaciones.auditada');
        Route::put('/{receta}/graduaciones', [RecetaController::class, 'guardarGraduaciones'])
            ->name('graduaciones.guardar.auditada');
    });

    /*
    | Vencimientos.
    |
    | `indice` NO audita: es un conteo, no una lectura de graduacion. Por eso no
    | lleva el sufijo `.auditada`. Ver docs/07.
    */
    Route::get('/v1/recetas-vencidas/indice', [VencimientoController::class, 'indice'])
        ->name('recetas-vencidas.indice');

    /* ---------------------------------------------------------------------
    | Catalogo
    | ------------------------------------------------------------------ */

    Route::prefix('/v1/armazones')->name('armazones.')->group(function (): void {
        Route::get('/', [ArmazonController::class, 'index'])->name('index');
        Route::post('/', [ArmazonController::class, 'store'])->name('store');
        Route::get('/{armazon}', [ArmazonController::class, 'show'])->name('show');
        Route::put('/{armazon}', [ArmazonController::class, 'update'])->name('update');
        Route::post('/{armazon}/desactivar', [ArmazonController::class, 'desactivar'])->name('desactivar');
    });

    Route::prefix('/v1/lentes')->name('lentes.')->group(function (): void {
        Route::get('/', [LenteController::class, 'index'])->name('index');
        Route::post('/', [LenteController::class, 'store'])->name('store');
        Route::get('/{lente}', [LenteController::class, 'show'])->name('show');
        Route::put('/{lente}', [LenteController::class, 'update'])->name('update');
        Route::post('/{lente}/desactivar', [LenteController::class, 'desactivar'])->name('desactivar');
    });

    /*
    | Verificacion de compatibilidad armazon/lente. No muta nada, asi que no
    | lleva policy de escritura: solo `verificarCompatibilidad`.
    */
    Route::post('/v1/compatibilidad', [LenteController::class, 'verificarCompatibilidad'])
        ->name('compatibilidad.verificar');

    /* ---------------------------------------------------------------------
    | Pedidos a laboratorio
    | ------------------------------------------------------------------ */

    Route::prefix('/v1/pedidos')->name('pedidos.')->group(function (): void {
        Route::get('/', [PedidoLaboratorioController::class, 'index'])->name('index');
        Route::post('/', [PedidoLaboratorioController::class, 'store'])->name('store');
        Route::get('/{pedido}', [PedidoLaboratorioController::class, 'show'])->name('show');
        Route::put('/{pedido}', [PedidoLaboratorioController::class, 'update'])->name('update');
        Route::post('/{pedido}/recibir', [PedidoLaboratorioController::class, 'recibir'])->name('recibir');
        Route::post('/{pedido}/cancelar', [PedidoLaboratorioController::class, 'cancelar'])->name('cancelar');
    });

    /* ---------------------------------------------------------------------
    | Control de calidad. Con bitacora: compara graduaciones.
    | ------------------------------------------------------------------ */

    Route::prefix('/v1/controles-calidad')->name('controles-calidad.')->group(function (): void {
        Route::get('/', [ControlCalidadController::class, 'index'])->name('index');

        /*
        | Comparar SIN guardar. El optometrista quiere ver las diferencias
        | contra lo pedido ANTES de poner el veredicto, no despues. Esta ruta
        | no muta nada, por eso no lleva `.auditada` al nombre.
        */
        Route::post('/comparar', [ControlCalidadController::class, 'comparar'])->name('comparar');

        Route::post('/', [ControlCalidadController::class, 'store'])->name('store.auditada');
        Route::get('/{control}', [ControlCalidadController::class, 'show'])->name('show.auditada');
    });

    /* ---------------------------------------------------------------------
    | Entregas y ajustes
    | ------------------------------------------------------------------ */

    Route::prefix('/v1/entregas')->name('entregas.')->group(function (): void {
        Route::get('/', [EntregaController::class, 'index'])->name('index');
        Route::post('/', [EntregaController::class, 'store'])->name('store');
        Route::post('/{entrega}/entregar', [EntregaController::class, 'entregar'])->name('entregar');
    });

    Route::prefix('/v1/ajustes')->name('ajustes.')->group(function (): void {
        Route::get('/', [AjusteController::class, 'index'])->name('index');
        Route::post('/', [AjusteController::class, 'store'])->name('store');
        Route::post('/{ajuste}/cerrar', [AjusteController::class, 'cerrar'])->name('cerrar');
    });

    /* ---------------------------------------------------------------------
    | Administracion
    | ------------------------------------------------------------------ */

    Route::middleware('admin')->prefix('/v1/admin')->name('admin.')->group(function (): void {
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::post('/usuarios/{usuario}/desactivar', [UsuarioController::class, 'desactivar'])
            ->name('usuarios.desactivar');

        Route::get('/bitacora', [BitacoraController::class, 'index'])->name('bitacora.index');
    });
});