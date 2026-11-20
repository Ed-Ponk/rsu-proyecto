# Auditoría de requisitos recuperados

La matriz de pantallas del [Avance 06 y Avance 07](MATRIZ_REQUISITOS_AVANCE_06_07.md) conserva los 17 identificadores inspeccionados visualmente durante V3. Los 105 resultados originales se conservan en su CSV. Los 24 casos añadidos están en el [plan de despliegue](../despliegue/RESULTADOS_DEP.csv). Las pruebas PHPUnit verifican reglas y contratos en el servidor.

| Fuente recuperada | Componentes | Regla y comprobación disponible |
| --- | --- | --- |
| Avance 02 y regresión existente | Vacaciones, asistencia y programación | `Avance02RestriccionesTest`, `AsistenciaPorTurnoTest`, `ProgramacionTest` en SQLite |
| Avance 03, commit `3c02963` | Grupos de personal y programación | Restricciones de asignación y validación de formularios mediante `ProgramacionTest` y `RSUOperationalWorkflowTest` |
| Avance 04, commits `6581fee` y `19cd5b7` | Cambios, motivos, programaciones por grupos, indicadores y bitácora | `RSUOperationalWorkflowTest` y la regresión de programación |
| Avance 05, commit `aaa3cca` | Usuarios, permisos, inicio y turnos | `AuthenticationTest`, `SeguridadYValidacionesTest`, `ReglasDeNegocioTest` |
| Avance 06 y plan del ingeniero | Login, programación, asistencia, GPS e historial del conductor | Nueve pantallas y 45 casos. `ConductorApiTest` verifica contratos de servidor |
| Avance 07 y plan V3 versionado | Zona, consulta, seguimiento y valoraciones ciudadanas | Ocho pantallas y 48 casos. Los casos ciudadanos de `ConductorApiTest` verifican la integración del servidor |

La [matriz de reglas de negocio](../MATRIZ_DE_TRAZABILIDAD.md) enlaza los módulos históricos con el código de servidor. Sigue pendiente cotejar los documentos docentes originales de los avances 01–05 con la versión implementada y ejecutar los escenarios de propagación y regresión en las interfaces completas. La nota de voz citada no especifica criterios nuevos.

El fetch encontró `origin/avance-07-api` en `7809486`, con sectores y foto de zona, una API ciudadana alternativa y pruebas adicionales. Sus migraciones crean `citizens` y `trip_ratings`, tablas presentes desde V3. Esa rama usa `rating` donde la integración usa `stars`. La integración pendiente debe extraer sectores y foto, unificar contratos y probar una migración de ambas variantes sobre copias antes de incorporar el historial de esa rama. El PR de esta entrega conserva la implementación verificada y ambos historiales permanecen disponibles.
