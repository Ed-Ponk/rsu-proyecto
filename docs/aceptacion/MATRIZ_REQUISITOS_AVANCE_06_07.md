# Matriz de pantallas RSU V3

| Pantalla | Fuente | Criterio de aceptación |
| --- | --- | --- |
| CON-01 | Avance 06 p. 1 | Login sin registro de conductor: correo y contraseña, mostrar/ocultar clave, mensajes de campos y servidor, configuración de servidor de laboratorio y persistencia segura de sesión. Solo conductores habilitados. |
| CON-02 | p. 2 | Mis programaciones agrupadas/ordenadas por fecha, insignia Hoy, zona, vehículo y placa, turno y días cuando estén registrados; detalle de asignación, menú, perfil con nombre, DNI, licencia, correo y teléfono propios; recarga y logout. |
| CON-03 | p. 3 | Programación de hoy: fecha, situación con/sin asignación, zona, vehículo con placa, ayudantes, turno y estado de asistencia individual. Inicio habilitado solo si el servidor confirma todas las condiciones. |
| CON-04 | p. 4 | Validación de asistencia: todos presentes habilitan confirmación; ausentes en rojo y motivo en ámbar, cancelación sin efectos, permiso GPS y servicio de ubicación verificados antes del inicio; ventana de anticipación de 30 minutos en fixture según el plan. Revalidar en servidor al confirmar. |
| CON-05 | p. 5 | Recorrido en curso con zona, vehículo, ayudantes, insignia En ruta, mapa dinámico, línea GPS creciente, coordenadas actuales, tiempo transcurrido y estado real de adquisición/sincronización. Captura automática nominal cada 5 s, incluso con pantalla bloqueada si el sistema lo permite. |
| CON-06 | p. 6 | Finalizar: resumen de asignación, turno, fecha/hora de inicio, observación obligatoria de 3 a 500 caracteres con contador, mensajes de validación visibles y opción de cancelar. |
| CON-07 | p. 6 | Confirmar cierre: registra hora de finalización del servidor, sincroniza o resuelve puntos pendientes según política aprobada, detiene captura y notificación GPS, muestra éxito y abre detalle solo tras confirmación del backend. |
| CON-08 | p. 7 | Historial propio: fecha, horas, zona, vehículo, estado y navegación al detalle; estado vacío correcto. |
| CON-09 | p. 8 | Detalle del recorrido propio: trayectoria persistida, inicio verde y fin rojo, horas, ayudantes, observación y mapa ampliado con conteo de ubicaciones; estado sin puntos sin inventar geometría. |
| CIU-01 | Avance 07 p. 1 | Registro con DNI, nombres, apellidos, correo, contraseña y zona; login con correo y contraseña, validaciones, sesión persistente, perfil y cierre de sesión. |
| CIU-02 | p. 2 | Encontrar zona según ubicación: permiso de ubicación en uso, mapa, posición actual, polígono geográfico de zona, nombre y descripción/sectores si existen; confirmar zona. Selección manual como alternativa ante permiso denegado, GPS apagado o ausencia de cobertura. |
| CIU-03 | p. 2 | Mis programaciones de la zona seleccionada: fechas, turnos, estado, filtro mensual, actualización, navegación Inicio/Historial/Perfil, estado vacío. Consultas limitadas al ámbito autorizado. |
| CIU-04 | p. 3 | Detalle de programación: fecha, zona, vehículo y placa, conductor, ayudantes, turno, estado y polígono real; opción Ver recorrido en mapa. Los campos de personal exigidos se prueban con datos ficticios; no devolver detalles privados adicionales. |
| CIU-05 | p. 3 | Seguimiento: estado En curso, hora/tiempo según el diseño, zona, vehículo, marcador del camión, trayectoria GPS que crece y posición propia si se autoriza. Leyenda diferenciada, última actualización y señal perdida. Sin recorrido, mostrar estado real sin fabricar marcador ni ruta. |
| CIU-06 | p. 4 | Calificar servicio finalizado: fecha, turno, vehículo, 1–5 estrellas, comentario opcional, validación, confirmación de envío y persistencia. No permitir valoraciones antes de la finalización ni manipulación desde el cliente. |
| CIU-07 | p. 4 | Historial de recorridos autorizados con fecha, zona, turno, estado finalizado y promedio/conteo de valoraciones calculados en servidor; distinguir ausencia de valoraciones. |
| CIU-08 | p. 5 | Detalle del recorrido con fecha, turno, zona, vehículo, conductor y ayudantes permitidos, trayectoria histórica con marcadores de inicio/fin y valoración/comentario según su alcance autorizado. |

[Resultados por caso](RESULTADOS_ACEPTACION.csv)

## Trazabilidad operativa

| Casos | Pantallas | Ruta o componente | Datos de comprobación |
| --- | --- | --- | --- |
| A1–A9 | CON-01 | `v1/salud`, `conductor/login`, SecureStore | Origen configurable y dos cuentas de distinto rol |
| A10–A17 | CON-02, CON-03, CON-08 | `conductor/programaciones`, `perfil`, `logout`, `recorridos` | Turno actual, futuro y conductor sin asignación |
| A18–A24 | CON-03, CON-04 | `programaciones/hoy`, `ServicioConductor`, panel de asistencias | Dos ayudantes, Turno vigente, presencia faltante y permisos |
| B1–B8 | CON-05 | `recorridos/actual`, `recorridos/{id}/puntos`, `gps.js`, historial panel | MySQL `trips`, `trip_points`, SQLite local y GPS ficticio |
| B9–B15 | CON-06, CON-07 | `recorridos/{id}/finalizar`, `Conductor.js` | Observación, confirmación y cola incidente |
| B16–B21 | CON-08, CON-09 | Historial, detalle, `Mapa.js`, panel de programación | Recorridos propios con puntos y sin ellos |
| C1–C14 | CIU-01 | `v1/ciudadano/registro`, `login`, `perfil`, `logout` | Cuentas separadas, DNI con cero inicial, hash y token |
| C15–C20 | CIU-02 | `zonas`, `zonas/ubicar`, `zona`, `ReglasZona` | Punto interno, borde, exterior y selección manual |
| C21–C26 | CIU-03, CIU-04 | `programaciones?mes=`, `programaciones/{id}` | Zonas distintas, cambio, meses y turno nocturno |
| D1–D9 | CIU-05 | Seguimiento con consulta de detalle cada 5 s | Puntos autorizados, última lectura, red y reapertura |
| D10–D17 | CIU-06 | `programaciones/{id}/valoracion`, integridad `trip_ratings` | Recorrido activo/completo, dos votantes y reenvío |
| D18–D22 | CIU-07, CIU-08 | `historial`, detalle, `citizen_zone_periods` | Historial autorizado, promedios y comentario propio |
| I1–I12 | Panel y ambas apps | `DemoV3Seeder`, API, PHP y MySQL | Turno actual, sesiones distintas, restauración y prueba de entorno |

La evaluación visual comprobó que las referencias usan barra azul, tarjetas de fecha y datos, navegación y mapas diferenciados. V3 recupera la barra y las pantallas, con navegación por acciones y pestañas. El mapa es vectorial y queda pendiente igualar el fondo vial, los controles de centrado y la iconografía de las referencias.
