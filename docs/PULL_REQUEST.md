# Integrar las aplicaciones móviles de RSU

Integra `avance-06-api` desde el historial de RSU, incorpora dos variantes Expo y conecta la consulta ciudadana a la programación y a los recorridos existentes. La versión de conductor exige un token personal y valida asignación y asistencia en Laravel.

## Arquitectura y reglas

Dos APK comparten código y cliente REST. Laravel mantiene las validaciones de asistencia, pertenencia al turno y cierre del recorrido. La consulta vecinal publica la zona, el horario y el estado. La ruta de historial del panel muestra el estado del recorrido móvil. El material para revisión está en `docs/REGLAS_DE_NEGOCIO.md`, `docs/MATRIZ_DE_TRAZABILIDAD.md` y `docs/API.md`.

## Pruebas y evidencias

- PHPUnit registró 176 aprobadas, 6 omitidas por configuración y 1 caso incompleto de KPI, con 1 757 aserciones. Pint, Vite y la exportación Expo se completaron.
- Se aplicaron las migraciones del avance sobre una copia SQLite de `main`. Se verificó por HTTP la escritura del recorrido, su recuperación en un proceso nuevo y su lectura web.
- Dos APK firmados de laboratorio se instalaron en Android 36. Se verificaron inicio, consulta En ruta, recuperación tras cierre forzado, ubicación ficticia, finalización y consulta Completado. Se recuperó la consulta tras retirar y restaurar la conexión ADB.

## Instalación, despliegue y límites

`docs/DESPLIEGUE_GRATUITO.md` explica SQLite, la ejecución local y la compilación Android. `INFORME_FINAL.md` contiene la matriz de aceptación y los hashes de los binarios locales. Quedan pendientes teléfono físico, iOS nativo, MySQL, despliegue HTTPS fuera del laboratorio y actualización de dependencias de compilación móvil. El GPS se registra a demanda.

Base `main`, origen `feat/mobile-platform-integration`. Para revisar los cambios específicos de integración, comparar desde `842c295`.
