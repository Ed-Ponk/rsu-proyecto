<?php

/*
|--------------------------------------------------------------------------
| Departamentos, provincias y distritos disponibles para las zonas
|--------------------------------------------------------------------------
| Alimenta los tres combos en cascada del formulario de zonas y centra el mapa en el distrito elegido.
| El valor de cada distrito es su centro aproximado [latitud, longitud]. Para atender otro departamento
| basta agregarlo aquí con la misma estructura (departamento => provincia => distrito => centro).
*/

return [

    // Centro y zoom con que se abre el mapa cuando todavía no hay distrito elegido.
    'mapa_inicial' => ['lat' => -6.7623, 'lng' => -79.8368, 'zoom' => 13],

    // Ubicación que aparece elegida al crear una zona (el distrito de la municipalidad).
    'por_defecto' => ['department' => 'Lambayeque', 'province' => 'Chiclayo', 'district' => 'José Leonardo Ortiz'],

    // Rectángulo (con margen) de cada departamento: las coordenadas de una zona deben caer dentro del que se elija.
    'limites_departamento' => [
        'Lambayeque' => ['lat_min' => -7.45, 'lat_max' => -5.30, 'lng_min' => -80.60, 'lng_max' => -78.95],
    ],

    // Límites de Perú: cualquier coordenada fuera de este rectángulo se rechaza.
    'limites_peru' => ['lat_min' => -18.4, 'lat_max' => -0.03, 'lng_min' => -81.4, 'lng_max' => -68.6],

    'departamentos' => [
        'Lambayeque' => [
            'Chiclayo' => [
                'Cayaltí' => [-6.8864, -79.5611],
                'Chiclayo' => [-6.7714, -79.8409],
                'Chongoyape' => [-6.6400, -79.3900],
                'Eten' => [-6.9086, -79.8633],
                'Eten Puerto' => [-6.9200, -79.8700],
                'José Leonardo Ortiz' => [-6.7623, -79.8368],
                'La Victoria' => [-6.7889, -79.8372],
                'Lagunas' => [-6.9800, -79.6150],
                'Monsefú' => [-6.8772, -79.8714],
                'Nueva Arica' => [-6.9000, -79.4500],
                'Oyotún' => [-6.8500, -79.3600],
                'Pátapo' => [-6.7381, -79.6853],
                'Picsi' => [-6.7167, -79.7500],
                'Pimentel' => [-6.8369, -79.9347],
                'Pomalca' => [-6.7667, -79.7833],
                'Pucalá' => [-6.7667, -79.6000],
                'Reque' => [-6.8636, -79.8222],
                'Santa Rosa' => [-6.8700, -79.9300],
                'Saña' => [-6.9200, -79.5500],
                'Tumán' => [-6.7456, -79.7131],
            ],
            'Ferreñafe' => [
                'Cañaris' => [-6.0500, -79.4000],
                'Ferreñafe' => [-6.6389, -79.7886],
                'Incahuasi' => [-6.2300, -79.3300],
                'Manuel Antonio Mesones Muro' => [-6.5700, -79.5700],
                'Pítipo' => [-6.5600, -79.7800],
                'Pueblo Nuevo' => [-6.4800, -79.7900],
            ],
            'Lambayeque' => [
                'Chóchope' => [-6.1400, -79.5800],
                'Íllimo' => [-6.4800, -79.8500],
                'Jayanca' => [-6.3950, -79.7650],
                'Lambayeque' => [-6.7011, -79.9061],
                'Mochumí' => [-6.5450, -79.8800],
                'Mórrope' => [-6.5410, -80.0000],
                'Motupe' => [-6.1500, -79.7167],
                'Olmos' => [-5.9833, -79.7500],
                'Pacora' => [-6.4000, -79.8400],
                'Salas' => [-6.2800, -79.6100],
                'San José' => [-6.7667, -79.9667],
                'Túcume' => [-6.5167, -79.8667],
            ],
        ],
    ],
];
