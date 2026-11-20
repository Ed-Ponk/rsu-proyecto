# API RSU

Usar `Accept: application/json`. Las mutaciones reciben JSON. Los recursos del conductor exigen `Authorization: Bearer <token>`. En caso de fallo Laravel devuelve `message` y, si corresponde, `errors`.

| Método y ruta | Datos y respuesta |
| --- | --- |
| GET `/up` | Disponibilidad del proceso Laravel |
| GET `/api/v1/recoleccion` | `zonas` activas, `programaciones` y `actualizado`; pasar `?zona=<id>` para consultar turnos |
| POST `/api/conductor/login` | `email`, `password`, `dispositivo?`; devuelve `token`, `conductor` y `config` |
| GET `/api/conductor/perfil` | Perfil del conductor propio |
| POST `/api/conductor/logout` | Revoca el token actual |
| GET `/api/conductor/programaciones[/hoy|/{id}]` | Lista, turno actual/relevante o detalle; incluye asistencia y motivo de bloqueo en el detalle |
| POST `/api/conductor/programaciones/{id}/recorrido` | Inicia y devuelve `201` y `data` |
| GET `/api/conductor/recorridos[/actual|/{id}]` | Historial paginado por `page`, recorrido activo o detalle |
| POST `/api/conductor/recorridos/{id}/puntos` | `puntos` con `lat`, `lng`, `momento` ISO 8601 y `precision?`; devuelve `registrados` y `descartados` |
| POST `/api/conductor/recorridos/{id}/finalizar` | `observacion` de 3 a 500 caracteres con configuración predeterminada |

Estados de consulta `programado`, `reprogramado`, `en_ruta`, `completado` y `sin_confirmacion`. En ruta informa que el conductor inició el recorrido. `inicio` y `fin` del turno son horas programadas en ISO 8601. Se consulta desde ayer hasta siete días hacia adelante, además de recorridos todavía activos. Errores frecuentes 401 sesión inválida, 403 rol, 404 recurso, 422 validación y 429 cuota.
# API ciudadana V3

Base `/api/v1/ciudadano`. Las respuestas privadas requieren token Sanctum de ciudadano. La unicidad del DNI y correo corresponde al ámbito ciudadano, separado de las cuentas del personal. [Contrato OpenAPI](openapi-v3.yaml).

| Método | Ruta | Contrato |
| --- | --- | --- |
| POST | `/registro` | DNI como cadena de 8 dígitos, `name`, `last_name`, `email`, `password` de 10–72 caracteres con máximo de 72 bytes y `zone_id` activa. Devuelve token y perfil. |
| POST | `/login` | `email`, `password`. Devuelve token y perfil. |
| GET, POST | `/zonas`, `/zonas/ubicar` | Lista de zonas activas o coincidencias para `lat`/ `lng`. Incluye el borde y retorna todas las coincidencias. |
| GET, POST | `/perfil`, `/logout`, `/zona` | Perfil propio, revocación o cambio de zona confirmado. |
| GET | `/programaciones?mes=AAAA-MM&page=1` | Mes por fecha de inicio del turno, limitado a la zona activa asignada. |
| GET | `/programaciones/{id}`, `/historial` | Detalle y viajes finalizados. Historial de zona actual y de zonas anteriores cuyos viajes cerraron durante el intervalo de pertenencia. |
| POST | `/programaciones/{id}/valoracion` | Entero `stars` 1–5 y `comment` opcional hasta 500 caracteres. Un voto por cuenta y recorrido completado. Un reenvío idéntico devuelve el mismo agregado. |

`GET /api/v1/salud` devuelve `{"servicio":"rsu","version":3}` para elegir el backend local. En la API del conductor, los puntos pueden incluir `operacion` UUID. Con UUID el servidor informa `registrados`, `repetidos` y `descartados`. La ventana de captura usa `captura_hasta`, que corresponde al final del turno de la programación.

Errores principales: 401 sin sesión, 403 token de otro rol, 404 fuera de alcance, 409 estado o voto incompatible, 422 validación y 429 límite de solicitudes.
