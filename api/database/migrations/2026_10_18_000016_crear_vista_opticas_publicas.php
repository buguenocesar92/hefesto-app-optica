<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vista `vw_opticas_publicas`: lo UNICO que se puede leer de una optica antes
 * de autenticarse.
 *
 * EXISTE PORQUE `opticas` NO LLEVA RLS
 * -------------------------------------
 * La tabla de empresas se consulta en el selector de ingreso, cuando todavia no
 * hay `app.tenant_id` que comprobar. Si llevara RLS, la funcion
 * `app_tenant_id()` lanzaria excepcion y el listado de opticas para entrar
 * devolveria un error siempre.
 *
 * QUE EXPONE
 * ----------
 * Tres columnas, y solo tres: `id`, `slug` y `nombre`. Con eso la pantalla de
 * entrada arma el selector y nada mas.
 *
 * QUE NO EXPONE
 * -------------
 * `correlacion_identidad` es la llave que liga a una persona con una optica en
 * Authentik. Publicarla sin autenticacion seria entregarle a cualquiera el mapa
 * de las empresas del sistema. `zona_horaria` y `activo` tampoco hacen falta
 * para elegir donde entrar.
 *
 * ESTA VISTA SE USA DE VERDAD
 * ---------------------------
 * La lee App\Models\Optica::publicas(), que es lo que consume
 * GET /api/v1/acceso/opticas, el endpoint que llena el selector de la pantalla
 * de entrada (web/app/pages/entrar.vue). Si alguna vez dejara de usarse, esta
 * migracion se elimina y se dice en la documentacion. Ver docs/03.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_opticas_publicas AS
            SELECT id, slug, nombre
            FROM public.opticas
            WHERE activo = true;

            COMMENT ON VIEW public.vw_opticas_publicas IS
            'Lo unico que se puede leer de una optica antes de autenticarse: id, slug y nombre, y solo opticas activas. La usa el selector de ingreso.';
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS public.vw_opticas_publicas');
    }
};