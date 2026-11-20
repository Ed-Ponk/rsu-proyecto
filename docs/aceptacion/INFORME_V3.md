# Informe de aceptación RSU V3

**NO APTO para certificar la exposición completa.** Los dos APK de laboratorio tienen evidencia en Android 36 con Laravel y MySQL. El plan de 105 casos conserva pendientes, fallos y bloqueos. Los resultados de PHPUnit se registran por separado.

## Alcance y evidencia

Se revisaron las 19 páginas de Avance 06, Avance 07 y del plan original del conductor. La matriz conserva los 17 identificadores de pantalla y los tres planes conservan los 105 casos. [Matriz de pantallas](MATRIZ_REQUISITOS_AVANCE_06_07.md), [resultados individuales](RESULTADOS_ACEPTACION.csv).

El servidor recorre registro, acceso por zona, valoración y privacidad. En dos emuladores, el conductor inició y cerró un recorrido con datos ficticios, y dos ciudadanos registraron votos de cuatro y cinco estrellas. El historial obtuvo 4,5 con dos votos. El APK, la API y el panel coincidieron en las 40 ubicaciones, sus horas y la observación. [Incidencias](REGISTRO_ERRORES.md).

En MySQL 8.4 aislado se verificaron migración desde cero, actualización desde el esquema anterior y respaldo/restauración. La copia conservó 28 programaciones. Dos procesos PHP y dos sesiones intentaron iniciar el mismo recorrido en paralelo. HTTP respondió 201 y 422, con un único recorrido. El envío repetido confirmó un punto nuevo y un duplicado. El cierre concurrente con otro punto conservó los dos puntos aceptados y el estado finalizado.

PHPUnit en SQLite registró 179 aprobadas, seis omitidas por funcionalidades de Jetstream deshabilitadas y 1 891 aserciones. El KPI pendiente se resolvió. En MySQL pasaron 17 pruebas de API con 179 aserciones. Pint y la exportación Expo de las dos variantes en Android e iOS se completaron. Quedan avisos de compilación en dependencias npm.

Los dos APK 1.1.0 tienen firma de laboratorio verificada y soportan `arm64-v8a` y `x86_64`. En este ensayo, 39 intervalos GPS simulados dieron mediana de 5 s y p95 de 44 s. La muestra incluyó pausas del ensayo y una corrección de sincronización. Doce solicitudes locales de salud dieron mediana de 28,2 ms y p95 de 145,1 ms. Queda pendiente medir latencia de seguimiento de extremo a extremo.

La matriz terminó con **44 aprobados, 2 fallidos, 4 bloqueados y 55 no ejecutados**. B2 conserva el fallo observado al esperar la primera lectura, aunque se comprobó el dibujo posterior de la ruta y el cierre. B7 confirmó la recuperación de la cola tras un minuto sin red. Las coordenadas simuladas permanecieron en un punto y el caso quedó fallido por falta de evidencia del nuevo tramo.

GitHub completó la comprobación existente del PR. El workflow adicional de MySQL y Expo queda en la entrega local como `rsu-v3-ci.yml`. La integración de GitHub devolvió HTTP 403 al intentar publicarlo.

## Límites de la entrega

- La ubicación fue emitida por el emulador. Quedan pendientes cadencia física, pantalla bloqueada, comprobación de la cola en un desplazamiento físico y dos ensayos completos.
- Las apps instaladas utilizaron el origen del PC vía la red del emulador, con puerto 8104. Quedan pendientes validación en teléfono, Wi-Fi físico, HTTPS externo e iOS nativo. El presupuesto contratado fue S/ 0.
- El mapa se dibuja a partir de datos geográficos persistidos. Quedan pendientes el fondo vial y otros detalles visuales del Avance 06 y 07.
- El cierre sin red exige reintentar tras reconectar. La consulta ciudadana conserva el último resultado de seguimiento mientras la sesión y pantalla están activas. Falta caché ciudadana durable.
- Las reglas técnicas de zona, privacidad y valoración son del laboratorio. La exposición de datos reales depende de autorización y retención municipal.

[Manual, preparación y arranque](../V3_LABORATORIO.md) · [Guion de exposición](GUION_EXPOSICION.md) · [PR #1](https://github.com/Ed-Ponk/rsu-proyecto/pull/1)
