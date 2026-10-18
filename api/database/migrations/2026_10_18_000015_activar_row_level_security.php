<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * AISLAMIENTO ENTRE OPTICAS: Row Level Security (RLS) de PostgreSQL.
 *
 * Patron tomado de la decision ya escrita y probada en
 * `apps/clinica-salud/docs/03-MULTITENANCY-Y-ROLES.md`. Se reutiliza tal
 * cual, sin inventar un cuarto enfoque.
 *
 * MECANISMO
 * ---------
 * 1. `public.app_tenant_id()` lee el valor de la variable de sesion
 *    `app.tenant_id`. Si no está definida, LANZA un error: no devuelve
 *    NULL ni cadena vacia. Falla de forma ruidosa, para que en el registro
 *    quede claro que una operacion se escapó del middleware.
 * 2. Cada tabla de negocio tiene una politica que exige
 *    `tenant_id = app_tenant_id()`, al leer (USING) y al escribir
 *    (WITH CHECK).
 * 3. `FORCE ROW LEVEL SECURITY` hace que la politica aplique tambien al
 *    dueño de la tabla. Sin FORCE, el dueño la saltaría: y en desarrollo
 *    el rol de la aplicacion suele ser el dueño, o sea que sin FORCE el
 *    aislamiento seria decorativo justo donde se prueba.
 * 4. La variable se fija con `set_config('app.tenant_id', ..., true)`, o
 *    sea con alcance de TRANSACCION. Al confirmar o revertir,
 *    PostgreSQL la descarta sola, de modo que el worker de colas, que
 *    reutiliza la misma conexion, no puede arrastrar la optica de otra
 *    empresa.
 *
 * LA TABLA `opticas` NO TIENE RLS, a propósito: se consulta en el ingreso,
 * cuando todavía no hay optica de sesión. Se compensa con la vista
 * `vw_opticas_publicas`, que expone solo tres columnas y solo opticas
 * activas.
 *
 * Lo que NO entra en la vista, y por qué:
 *   - `correlacion_identidad`, porque es la llave que liga a una persona con
 *     una optica en Authentik. Publicarla antes de autenticarse sería entregar
 *     el mapa de la empresa.
 *   - `zona_horaria`, porque no hace falta para elegir donde entrar.
 *
 * REQUISITO DE DESPLIEGUE: el rol que usa la aplicacion NO puede ser
 * superusuario ni tener BYPASSRLS. Con superusuario, PostgreSQL ignora las
 * politicas por completo. Lo comprueba
 * `php artisan optica:verificar-aislamiento`.
 */
return new class extends Migration
{
    /**
     * Tablas con datos de negocio, y por lo tanto con RLS.
     *
     * `opticas` y `sessions` NO estan y no deben estar: se consultan antes de
     * que exista sesion. Ver la cabecera.
     *
     * @var list<string>
     */
    private array $tablasProtegidas = [
        'usuarios',
        'pacientes',
        'recetas',
        'graduaciones',
        'armazones',
        'lentes',
        'pedidos_laboratorio',
        'pedido_items',
        'controles_calidad',
        'entregas',
        'ajustes',
        'accesos_recetas',
    ];

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.app_tenant_id() RETURNS uuid
            LANGUAGE plpgsql
            STABLE
            AS $$
            DECLARE
                valor text;
            BEGIN
                valor := current_setting('app.tenant_id', true);

                IF valor IS NULL OR valor = '' THEN
                    RAISE EXCEPTION USING
                        ERRCODE = 'insufficient_privilege',
                        MESSAGE = 'No hay contexto de optica en la conexion (app.tenant_id no está definida). Se aborta la operacion para no exponer datos de otra optica.',
                        HINT = 'La peticion no pasó por App\Http\Middleware\ResolverTenant, o la operacion se ejecutó fuera de una transaccion.';
                END IF;

                RETURN valor::uuid;
            END
            $$;

            COMMENT ON FUNCTION public.app_tenant_id() IS
            'Devuelve la optica de la peticion. Lanza excepcion si no hay contexto: el aislamiento falla cerrado.';
        SQL);

        foreach ($this->tablasProtegidas as $tabla) {
            $existe = DB::selectOne('SELECT to_regclass(?) AS tabla', ['public.' . $tabla]);

            // to_regclass devuelve NULL si la tabla todavia no existe.
            if ($existe === null || $existe->tabla === null) {
                continue;
            }

            DB::unprepared('ALTER TABLE public.' . $tabla . ' ENABLE ROW LEVEL SECURITY');
            DB::unprepared('ALTER TABLE public.' . $tabla . ' FORCE ROW LEVEL SECURITY');

            $nombre = 'politica_aislamiento_' . $tabla;

            DB::unprepared(<<<SQL
                DROP POLICY IF EXISTS {$nombre} ON public.{$tabla};

                CREATE POLICY {$nombre} ON public.{$tabla}
                    FOR ALL
                    TO PUBLIC
                    USING (tenant_id = public.app_tenant_id())
                    WITH CHECK (tenant_id = public.app_tenant_id());
            SQL);
        }

        $this->verificarColumnaTenant();
    }

    public function down(): void
    {
        foreach ($this->tablasProtegidas as $tabla) {
            $existe = DB::selectOne('SELECT to_regclass(?) AS tabla', ['public.' . $tabla]);

            if ($existe === null || $existe->tabla === null) {
                continue;
            }

            DB::unprepared('DROP POLICY IF EXISTS politica_aislamiento_' . $tabla . ' ON public.' . $tabla);
            DB::unprepared('ALTER TABLE public.' . $tabla . ' NO FORCE ROW LEVEL SECURITY');
            DB::unprepared('ALTER TABLE public.' . $tabla . ' DISABLE ROW LEVEL SECURITY');
        }

        DB::unprepared('DROP FUNCTION IF EXISTS public.app_tenant_id()');
    }

    /**
     * Falla ruidosamente si alguna tabla de la lista no tiene
     * `tenant_id`. Sin esto, un error de tipeo dejaría una tabla de datos
     * de negocio sin politica y la migracion pasaria igual.
     */
    private function verificarColumnaTenant(): void
    {
        foreach ($this->tablasProtegidas as $tabla) {
            $existe = DB::selectOne(
                'SELECT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = ''public'' AND table_name = ? AND column_name = ''tenant_id''
                 ) AS tiene_columna',
                [$tabla]
            );

            if ($existe === null || ! $existe->tiene_columna) {
                throw new RuntimeException(
                    'La tabla public.' . $tabla . ' no tiene columna tenant_id y no puede protegerse con RLS. '
                    . 'Revise la migracion antes de continuar.'
                );
            }
        }
    }
};