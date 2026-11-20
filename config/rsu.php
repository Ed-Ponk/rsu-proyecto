<?php

return [
    // Activar solamente en el entorno aislado con identidades y rutas ficticias.
    'demostracion' => (bool) env('RSU_DEMO_DATA', false),
];
