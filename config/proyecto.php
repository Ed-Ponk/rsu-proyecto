<?php

/*
|--------------------------------------------------------------------------
| Parámetros propios del sistema de residuos sólidos
|--------------------------------------------------------------------------
| Valores que el negocio puede ajustar sin tocar el código de los controladores.
*/

return [

    'municipalidad' => 'Municipalidad Distrital de José Leonardo Ortiz',

    'contratos' => [
        // Meses que deben pasar desde que vence un contrato temporal para volver a contratar a la misma persona con otro temporal.
        'meses_entre_temporales' => 2,
    ],

    'vehiculos' => [
        'anio_minimo' => 1990,
        'max_imagenes' => 12,
        'imagen_max_kb' => 2048,
        'imagen_formatos' => ['jpeg', 'jpg', 'png', 'gif'],
    ],

    'personal' => [
        'edad_minima' => 18,
    ],

    'asistencia' => [
        // Marcación con DNI: espera mínima entre dos marcaciones de la misma persona (evita el doble clic).
        'segundos_entre_marcaciones' => 60,
        // Intentos fallidos de DNI/contraseña permitidos por minuto antes de bloquear temporalmente.
        'intentos_marcacion' => 5,

        // Minutos después del inicio del turno en que la entrada aún NO cuenta como tardanza.
        'minutos_tolerancia' => 10,
        // Minutos antes del inicio de un turno desde los que se puede marcar su entrada (llegar temprano).
        'minutos_anticipacion_entrada' => 30,
        // Minutos después del fin del turno en que todavía se acepta su salida (horas extra).
        'minutos_gracia_salida' => 180,
    ],

];
