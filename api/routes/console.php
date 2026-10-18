<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
|
| Los comandos de `app/Console/Commands` se registran solos: Laravel 11 y
| posteriores los descubre en el directorio de comandos. Lo que se declara aca
| es CUANDO corre cada uno, que es la parte que hay que poder leer de un vistazo
| antes de tocar produccion.
|
| Ver docs/05.
|
| `optica:verificar-aislamiento` y `optica:revisar-vencimientos` se pueden
| correr a mano en cualquier momento; ver sus archivos para que hacen y que no
| hacen.
|
*/

Schedule::command('optica:verificar-aislamiento')
    ->dailyAt('04:10')
    /*
    | Escribe filas de prueba y las borra. Contra una base de produccion con
    | pacientes reales se puede correr, pero conviene elegir una hora en que
    | nadie este operando: las filas aparecen un instante.
    */
    ->withoutOverlapping();

/*
| La revision se corre SINCRONICA a proposito: cuenta recetas y no modifica
| ninguna. Si la optica crece, se cambia a `optica:revisar-vencimientos` sin
| `--sincrono` para que cada optica se revise en su propio trabajo encolado.
*/
Schedule::command('optica:revisar-vencimientos --sincrono')
    ->dailyAt('06:40')
    ->withoutOverlapping();

/*
| No hay tareas que purguen recetas ni bitacora.
|
| Los dias de conservacion estan en
| `config('optica.retencion.recetas_dias')` y
| `config('optica.retencion.accesos_recetas_dias')`, y NACEN EN NULL porque no
| se pudo verificar que plazo corresponde. Con las dos vacias la aplicacion no
| borra nada por su cuenta: borrar una graduacion porque paso un numero
| inventado es peor que no borrar nunca. Ver docs/07.
*/

Schedule::command('queue:prune-batches')
    ->daily();