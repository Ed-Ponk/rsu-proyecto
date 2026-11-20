# Matriz de trazabilidad

| Regla | Ruta / interfaz | Cobertura automatizada |
| --- | --- | --- |
| RN-01 | `/admin/usuarios`, `/login`, `/api/conductor/perfil` | `AuthenticationTest`, `CorreccionesDeAuditoriaTest`, `SeguridadYValidacionesTest`, `ConductorApiTest` |
| RN-02–04 | Panel de catálogos, vehículos y personal | `ReglasDeNegocioTest`, `RSUArchitectureTest`, `SeguridadYValidacionesTest` |
| RN-05–06 | `/admin/contratos`, `/admin/asistencias` y marcación | `ReglasDeNegocioTest`, `AsistenciaPorTurnoTest`, `CorreccionesDeAuditoriaTest` |
| RN-07 | `/admin/vacaciones` | `ProgramacionTest`, `Avance02RestriccionesTest` |
| RN-08–09 | Panel de zonas, turnos, feriados, programación y cambios | `ZonasTest`, `ProgramacionTest`, `RSUOperationalWorkflowTest` |
| RN-10 | Acceso del conductor, `login`, `perfil`, `logout` | `ConductorApiTest` |
| RN-11–12 | Mis recorridos, `programaciones/{id}/recorrido`, `recorridos/{id}/puntos`, `finalizar` e historial del panel | `ConductorApiTest` |
| RN-13 | Recolección por zona, `GET /api/v1/recoleccion` | `ConductorApiTest::test_la_consulta_ciudadana_refleja_el_recorrido_sin_datos_personales` |
