<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Controlador base.
 *
 * Aporta `authorize()`, que en esta aplicacion devuelve 404 cuando la policy
 * rechaza. Ver bootstrap/app.php y docs/07.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;
}