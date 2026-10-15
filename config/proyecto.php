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

];
