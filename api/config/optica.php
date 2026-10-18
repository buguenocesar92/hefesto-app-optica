<?php

declare(strict_types=1);

/*
 * Este archivo concentra TODAS las reglas de negocio que no son codigo.
 *
 * La regla del estudio es una sola: si un valor no se pudo verificar, se deja
 * en `null` y se marca como pendiente. Nunca se escribe un numero plausible.
 * Un plazo de vigencia inventado en una receta es peor que una receta sin
 * plazo: el primero hace creer que hay respaldo normativo y el segundo deja
 * claro que falta decidirlo.
 */

/**
 * Lee un entero del entorno distinguiendo "no configurado" de "cero".
 *
 * `env('X')` devuelve la cadena vacia cuando la variable existe pero esta
 * vacia. Con un `(int) env('X')` eso pasaria a ser 0, que es un plazo
 * perfectamente valido y por lo tanto un dato falso. Por eso se mira el
 * valor crudo antes de convertir.
 */
$enteroOpcional = static function (?string $nombre): ?int {
    $crudo = env($nombre);

    if ($crudo === null || $crudo === '') {
        return null;
    }

    return (int) $crudo;
};

/**
 * Lee un decimal del entorno con la misma distincion. Ver arriba.
 */
$decimalOpcional = static function (?string $nombre): ?float {
    $crudo = env($nombre);

    if ($crudo === null || $crudo === '') {
        return null;
    }

    return (float) $crudo;
};

return [

    /*
    |--------------------------------------------------------------------------
    | Zona horaria y formato
    |--------------------------------------------------------------------------
    */

    'zona_horaria' => env('APP_TIMEZONE', 'America/Santiago'),

    'locale' => 'es_CL',

    /*
    |--------------------------------------------------------------------------
    | VIGENCIA DE LA RECETA
    |--------------------------------------------------------------------------
    |
    | ESTA SECCION NACCE VACIA Y ESO ES UNA DECISION, NO UN OLVIDO.
    |
    | Una receta optica tiene plazo de vigencia, y ese plazo depende de la edad
    | del paciente: en la gente joven la graduacion cambia mas rapido, y por eso
    | el plazo que le vale a un adulto no le sirve a un nino.
    |
    | El plazo concreto por franja etaria es NORMA SANITARIA CHILENA. En la
    | maquina donde se escribio esta aplicacion no se pudo verificar contra la
    | norma vigente cual es ese plazo, ni que franjas etarias usa. Escribir un
    | numero aqui seria inventar una obligacion legal, y una obligacion legal
    | inventada es de las pocas cosas que en este producto no tienen plan B: el
    | optico despacharia lentes sobre una receta que en realidad no estaba
    | vigente.
    |
    | Que significa `null`: NO HAY PLAZO CONFIGURADO. La aplicacion no bloquea
    | nada por omision, no vence nada sola y avisa en la interfaz de que el
    | plazo esta pendiente de verificacion.
    |
    | Quien lo completa: la optica, con su revision normativa y legal. Los
    | limites de las franjas tambien estan pendientes: son un molde razonable
    | para cargar el dato, no una clasificacion normativa.
    |
    | Ver docs/06 y docs/07.
    |
    */

    'receta' => [
        'vigencia_por_edad' => [
            [
                'etiqueta' => 'Hasta los 12 anios',
                'desde' => 0,
                'hasta' => 12,
                'dias' => $enteroOpcional('OPTICA_VIGENCIA_HASTA_12_ANIOS'),
            ],
            [
                'etiqueta' => 'De 13 a 17 anios',
                'desde' => 13,
                'hasta' => 17,
                'dias' => $enteroOpcional('OPTICA_VIGENCIA_13_A_17_ANIOS'),
            ],
            [
                'etiqueta' => 'De 18 a 59 anios',
                'desde' => 18,
                'hasta' => 59,
                'dias' => $enteroOpcional('OPTICA_VIGENCIA_18_A_59_ANIOS'),
            ],
            [
                'etiqueta' => 'De 60 anios y mas',
                'desde' => 60,
                'hasta' => 200,
                'dias' => $enteroOpcional('OPTICA_VIGENCIA_DESDE_60_ANIOS'),
            ],
        ],

        /*
        | Cuantos dias antes del vencimiento se avisa. Es una decision interna
        | de la optica, no una obligacion: no nace en `null` porque el aviso
        | anticipado no cambia la validez de nada, solo avisa antes.
        */
        'dias_de_aviso' => (int) env('OPTICA_DIAS_DE_AVISO', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | COMPATIBILIDAD ENTRE ARMazon Y LENTE
    |--------------------------------------------------------------------------
    |
    | Esta es la verificacion que sostiene el producto. Un lente se mecaniza
    | sobre una pastilla circular y despues se monta en el armazon. Si el
    | diametro de la pastilla no alcanza para cubrir la caja del armazon mas
    | el borde que el armazon necesita, la hecha no sirve: el lente no entra,
    | o entra y se ve cortado.
    |
    | LO QUE SE CALCULA AQUI Y LO QUE NO
    | ----------------------------------
    | La parte geometrica sale de las medidas del armazon y es un hecho:
    |
    |     ancho_de_caja = ancho_varilla + puente
    |
    | La parte que NO sale de los datos es el margen que el lente necesita para
    | cubrir el centro optico y todavia alcanzar el borde. Ese margen depende
    | del tipo de lente y de la distancia interpupilar del paciente, y varia
    | entre fabricantes. En esta maquina NO se pudo verificar contra la norma
    | ni contra las fichas tecnicas de los fabricantes que valor corresponde,
    | asi que `diametro_efectivo_minimo_mm` NACE EN NULL.
    |
    | Que significa `null`: NO SE PUEDE VERIFICAR todavia. El servicio NO
    | devuelve "compatible" ni "incompatible" en ese caso: devuelve
    | `no_verificable` y el desglose de lo que si pudo calcular. Un sistema
    | que devuelve "si" sin tener el dato esta adivinando.
    |
    | Ver docs/06, la clase App\Services\Optica\VerificadorDeCompatibilidad y
    | sus pruebas unitarias.
    |
    */

    'compatibilidad' => [
        'diametro_efectivo_minimo_mm' => $decimalOpcional('OPTICA_DIAMETRO_EFECTIVO_MINIMO_MM'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PEDIDO A LABORATORIO
    |--------------------------------------------------------------------------
    |
    | El plazo prometido NO se inventa: es comercial y cambia de laboratorio a
    | laboratorio y de temporada a temporada. Nace en `null` y lo carga la
    | optica, por pedido o por laboratorio.
    |
    | `dias_promesa_por_omision` es el unico valor que se puede usar como
    | propuesta al crear un pedido. Si tampoco hay ese, el campo queda vacio y
    | el pedido se puede guardar igual: la fecha prometida la negocia la optica
    | con su laboratorio, no el software.
    |
    */

    'laboratorio' => [
        'dias_promesa_por_omision' => $enteroOpcional('OPTICA_DIAS_LABORATORIO'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CONTROL DE CALIDAD AL RECIBIR
    |--------------------------------------------------------------------------
    |
    | Tolerancia con que se acepta que la graduacion que llega del laboratorio
    | sea la que se pidio. Nace en `null` porque es una decision clinica del
    | optometrista, no una constante del software: hay tolerancias que el
    | laboratorio considera correctas y que el profesional no acepta.
    |
    | Mientras sea `null`, el control de calidad SI se puede registrar y SI
    | detecta diferencias: compara valor por valor y las muestra. Lo que no
    | hace es emitir un veredicto automatico de conforme o no conforme. El
    | veredicto lo pone la persona.
    |
    */

    'control_calidad' => [
        'tolerancia_dioptrias' => $decimalOpcional('OPTICA_TOLERANCIA_DIOPTRIAS'),
        'tolerancia_eje_grados' => $enteroOpcional('OPTICA_TOLERANCIA_EJE_GRADOS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CONSERVACION
    |--------------------------------------------------------------------------
    |
    | ESTOS PLAZES NACEN EN NULL Y NO TIENEN VALOR POR OMISION.
    |
    | La graduacion de una receta es informacion de salud. Cuanto tiempo se
    | conserva depende del contrato de tratamiento de datos que la optica
    | firme con sus pacientes y de la normativa sectorial que le aplique. No
    | se pudo verificar en esta maquina el plazo que corresponde, asi que no se
    | escribe.
    |
    | Con `null`, la aplicacion NO purga nada por su cuenta. Borrar la
    | graduacion de un paciente porque paso un numero inventado es peor que no
    | borrar nunca. Ver docs/07.
    |
    */

    'retencion' => [
        'recetas_dias' => $enteroOpcional('OPTICA_RETENCION_RECETAS_DIAS'),
        'accesos_recetas_dias' => $enteroOpcional('OPTICA_RETENCION_ACCESOS_RECETAS_DIAS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bitacora de accesos
    |--------------------------------------------------------------------------
    |
    | Umbrales para revisar. Son heuristicas de revision, no deteccion de
    | incidentes: un volumen alto de intentos denegados suele significar que
    | alguien esta probando el sistema con el rol equivocado, o que una persona
    | esta trabajando fuera de rol. Ver docs/07.
    |
    */

    'alerta_denegados_por_hora' => (int) env('OPTICA_ALERTA_DENEGADOS_POR_HORA', 20),
    'alerta_denegados_por_usuario' => (int) env('OPTICA_ALERTA_DENEGADOS_POR_USUARIO', 5),

    /*
    |--------------------------------------------------------------------------
    | Entregas y ajustes
    |--------------------------------------------------------------------------
    |
    | Todo lente se ajusta despues de entregado. `dias_de_garantia_sugeridos`
    | NO es un plazo legal: es la sugerencia que el sistema muestra para
    | ordenar la cola de ajustes. Nace en `null` para que la optica lo defina.
    |
    */

    'entrega' => [
        'dias_de_garantia_sugeridos' => $enteroOpcional('OPTICA_DIAS_DE_GARANTIA_SUGERIDOS'),
    ],

];