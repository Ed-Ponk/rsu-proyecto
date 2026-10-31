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

    'vacaciones' => [
        // Cupo de días de vacaciones por persona y año (solo las solicitudes aprobadas lo descuentan).
        'dias_por_anio' => 30,
    ],

    // Administrador inicial. En desarrollo se usa admin@rsu.test; en producción son obligatorios.
    'admin' => [
        'correo' => env('ADMIN_EMAIL'),
        'clave' => env('ADMIN_PASSWORD'),
    ],

    'turnos' => [
        // Duración permitida de un turno (los que cruzan la medianoche se miden hasta el día siguiente).
        'duracion_minima_horas' => 1,
        'duracion_maxima_horas' => 12,
    ],

    'feriados' => [
        // Años permitidos al registrar un feriado a mano.
        'anio_minimo' => 2020,
        'anios_hacia_adelante' => 10,
    ],

    'zonas' => [
        // Superficie permitida de una zona (km²): evita figuras diminutas por error o zonas que abarcan media provincia.
        'area_minima_km2' => 0.0001,
        'area_maxima_km2' => 150,
        // Cuánto puede invadir una zona a otra, como fracción de la más pequeña de las dos (0.01 = 1 %).
        // Una tolerancia mínima permite que dos zonas vecinas compartan un borde dibujado a mano.
        'solape_maximo' => 0.01,
    ],

    'programacion' => [
        // Aviso al programar a quien tiene al menos N faltas en los últimos X días.
        'faltas_alerta' => 3,
        'faltas_dias' => 30,
        // Aviso cuando el contrato vence pocos días después del último día programado.
        'contrato_por_vencer_dias' => 15,
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
