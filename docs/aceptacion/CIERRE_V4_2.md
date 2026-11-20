# Cierre V4.2

Referencia V4.1: `cfb0f0a`. Ancestro común con Avance 07: `842c295`. Revisión paralela: `7809486`.

| Área | Archivos o entidades | Conciliación |
| --- | --- | --- |
| Sectores y fotografía | ZonaController, Zona, SectorZona, formularios, zone_sectors y zones.photo_path | Se recuperó el módulo del Avance 07. Una edición de un cliente anterior conserva sectores si omite el campo. |
| Ciudadanía | Citizen, Ciudadano, SoloCiudadano, citizens | Se mantiene el contrato de los APK existentes. Se conserva el estado activo de la rama paralela y se rechazan cuentas desactivadas. |
| Votos | trip_ratings, CalificacionRecorrido, CiudadanoController | La columna paralela rating se adapta a stars sin recrear la tabla. Se conservan identificadores, comentarios y unicidad. |
| Historial y GPS | citizen_zone_periods, trip_points | Se conserva el historial V4.1. Las cuentas importadas inician su primer periodo en la fecha de conciliación. La trayectoria GPS conserva los UUID existentes. |
| Tokens | Sanctum | Los tokens de Ciudadano de Avance 07 se revocan. Los usuarios deben iniciar sesión de nuevo. Los tokens canónicos mantienen su modelo y ciclo de vida. |
| API | routes/api.php y controladores ciudadanos | Se mantiene la ruta canónica V4.1 y se añaden sectores y foto. La API alternativa requiere revisión de equivalencia antes de certificar clientes que la usaban. |

## Verificación de migraciones

Se ejecutaron tres escenarios MySQL aislados. La instalación limpia completó todas las migraciones. La copia de V4.1 mantuvo 30 programaciones, nueve asistencias, 40 puntos, dos ciudadanos y dos votos. En una base con las migraciones originales de Avance 07 se conservaron el ID 71 del ciudadano, el voto 72 de cuatro estrellas, el hash de la contraseña, el estado inactivo, el nombre del sector y la referencia de foto. El ingreso V4.1 devolvió HTTP 200 y promedio 4,5. El ingreso de la cuenta inactiva dio HTTP 422. El ensayo de foto verificó la referencia SQL, no la disponibilidad de un archivo de una instalación real.

La migración V3 conserva la ruta de instalación original. Si todavía está pendiente y el registro de migraciones identifica ambas tablas originales del Avance 07, verifica sus columnas y evita recrearlas. La migración posterior conserva el estado de las cuentas y registra la transformación. El regreso a un esquema histórico debe usar el respaldo previo.

## Cierre sin conexión

El conductor guarda la observación y la operación en SQLite, invalida el control local de captura y conserva la cola previa. Al reconectar, la app envía la cola antes de solicitar el cierre. Repetir el mismo UUID y la misma observación obtiene la confirmación original. Dos operaciones de cierre incompatibles quedan en conflicto. La observación local sigue disponible para revisión.

En Android 36 emulado, el conductor solicitó el cierre con la red cortada, terminó el proceso y volvió a abrirlo. El servidor mantuvo 34 puntos y el recorrido activo. Al recuperar la red, confirmó 12 coordenadas nuevas con 12 UUID diferentes, preservó la observación y cerró con 46 puntos. El reenvío del mismo cierre mantuvo su hora y una observación distinta con el mismo UUID recibió HTTP 409. La evidencia corresponde al APK de ensayo compilado de `6b33d2f` y al GPS simulado.

## Pendientes de cierre

- Conciliar dos bases reales con identidades duplicadas requiere las dos fuentes y un plan de correspondencia por DNI, correo, zona y recorrido. El ensayo actual cubre la actualización individual de cada esquema.
- Repetir el cierre offline con expiración de token y un reinicio completo del dispositivo físico.
- Registrar la ejecución acumulada de los 129 casos y las operaciones del panel. Repetir GPS e integración sobre la misma compilación candidata.
- Validar LAN con otro cliente. Para las pruebas físicas, disponer de un Android autorizado y una red privada. Para HTTPS, habilitar acceso autorizado desde otra red con una cuenta del responsable.
- Congelar compilaciones, ejecutar dos ensayos integrales y actualizar el PR con un resultado respaldado por evidencia.

## Resultados de esta ronda

- Las regresiones completas de SQLite y MySQL registraron 187 aprobadas y seis omitidas cada una. La primera ejecución completa en MySQL expuso un fixture de asistencias que fijaba el ID del turno. La segunda lo obtuvo del catálogo y pasó. El formulario HTTP confirmó sector y fotografía, y la imagen siguió disponible tras reiniciar PHP.
- La compilación `5b30cbb` mostró la capa vial y permitió cotejar horas, observación y 46 coordenadas con el panel. Los dos APK 1.2.0 del paquete actualizado proceden de `817086b`. El manifiesto y la matriz identifican la compilación usada en cada prueba.
- B7 reprodujo un arranque offline que requería volver a abrir la app y un registro de ubicación que podía quedar obsoleto. Con las dos correcciones, la versión `817086b` recuperó 12 coordenadas tras reconectar en 8,3 segundos. Se cotejaron ambos APK y el panel. Los 12 UUID de MySQL fueron distintos.
- B2 volvió a pasar con `817086b` sobre una copia migrada de Avance 07. La pantalla mostró cero puntos y búsqueda. MySQL recibió la primera coordenada simulada y el mismo APK mostró el estado de registro.
- La [matriz acumulada](RESULTADOS_V4_2.csv) conserva V3 y V4.1, y registra ocho casos aprobados en esta ronda, 15 bloqueados y 106 pendientes. El ensayo de cierre queda identificado por separado.
- El servidor de ensayo se verificó desde Windows en `http://127.0.0.1:8111` y desde Android emulado en `http://10.0.2.2:8111`. MySQL permaneció en loopback. Infraestructura contratada adicional: S/ 0. La prueba física, LAN/hotspot y HTTPS externo siguen sin certificar.
