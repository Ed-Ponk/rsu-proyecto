# Reglas de negocio

Las filas agrupan controles relacionados por flujo. Las condiciones, restricciones y mensajes de cada campo se encuentran en las validaciones de los componentes enlazados por ruta en la matriz.

| ID | Actor y condición | Resultado y excepción | Estado y fuente |
| --- | --- | --- | --- |
| RN-01 | Administrador activo, operador activo con permiso del módulo | El panel valida sesión, estado y operación. Personal y token móvil reciben rechazo. El administrador final y el propio usuario tienen restricciones de eliminación y desactivación. | Existente. `FortifyServiceProvider`, `VerificarPermiso`, `SinTokenEnElPanel`, `UsuarioController`. |
| RN-02 | Operador con permisos de catálogo | Catálogos con unicidad por nombre o código según entidad. Modelos pertenecen a una marca. Las asociaciones vigentes restringen la eliminación. | Existente. Controladores de color, marca, modelo y tipo, migraciones de integridad. |
| RN-03 | Operador de vehículos | Placa y código únicos, año en rango, referencias válidas, imagen en formato y tamaño aceptados. Los problemas de registro se devuelven al formulario. | Existente. `VehiculoController`, `Vehiculo`. |
| RN-04 | Operador de personal | DNI único de ocho dígitos, mayoría de edad según configuración y licencia para conductor. Los tipos base tienen protección contra borrado. | Existente. `PersonalController`, `TipoPersonalController`. |
| RN-05 | Operador de contratos y persona existente | Un contrato activo por persona, temporales con plazo y separación, vencimiento cierra vigencia y afecta disponibilidad. Se rechazan fechas y solapamientos inválidos. | Existente. `ReglasContrato`, `Contrato`, `ContratoController`. |
| RN-06 | Operador de asistencia o personal habilitado para marcación | Contrato vigente y turno válido. Entrada/salida alterna, se calcula tardanza, se conserva turno de jornada nocturna y se rechaza marcación futura. | Existente. `ServicioAsistencia`, `ResolverTurno`, `AsistenciaController`. Afecta al conductor. |
| RN-07 | Operador de vacaciones con persona, inicio y días | Se verifica cupo anual, año único y solapamientos. Aprobar consume saldo, rechazar libera el intervalo, las resueltas quedan cerradas. | Existente. `ReglasVacacion`, `VacacionController`. |
| RN-08 | Operador de zonas, turnos y feriados | Zona con ubigeo y polígono válido, turnos con intervalo, feriados por fecha. La programación respeta incompatibilidades y contempla turnos nocturnos. | Existente. `ReglasZona`, `ReglasProgramacion`, controladores correspondientes. |
| RN-09 | Operador con permiso de programación y recursos disponibles | Asigna zona, turno, vehículo y equipo. Valida actividad, cargo, jornada, contrato, vacaciones y cruces según el flujo. Se registran cambios y motivos. | Existente. `ReglasProgramacion`, `ServicioCambios`, `ProgramacionController`, `CambioController`. Afecta a ambas apps. |
| RN-10 | Conductor activo con correo y clave de marcación válidos | Recibe token Sanctum con habilidad `conductor`. Solo accede a sus datos y asignaciones. Expiración a 24 h para tokens nuevos, revocación al salir o perder habilitación. | Existente, endurecida. `AccesoController`, `SoloConductor`. |
| RN-11 | Conductor asignado, turno pendiente y asistencia completa | Inicio dentro de la ventana de turno configurada. No se permite iniciar sobre un recorrido existente o con otro recorrido activo. | Existente. `ServicioConductor::puedeIniciar`, `RecorridoController::iniciar`. Afecta a ambas apps. |
| RN-12 | Conductor propietario con recorrido en curso | Acepta ubicaciones válidas del recorrido y registra cierre con observación. Valida coordenadas, lote, fechas y longitud. Un recorrido cerrado no acepta más puntos. | Existente. `RecorridoController`, `PuntoRecorrido`, `Recorrido`. |
| RN-13 | Vecino elige una zona activa | Proyección de horarios y estados de programación/recorrido, sin datos de personal, vehículo ni GPS. Si el turno terminó sin recorrido, queda sin confirmación. | Proyección nueva de reglas existentes, destinada al laboratorio. Requiere aprobación municipal antes de habilitarla públicamente. |

No hay entidad de cliente, solicitud, pago, valoración o asignación por domicilio en el modelo inspeccionado. Están pendientes de definición si el municipio los requiere.

## Condiciones y excepciones de los flujos operativos

| Regla | Datos y condición | Resultado o mensaje identificable |
| --- | --- | --- |
| RN-05.1 | Persona, tipo, inicio y fin si es temporal. Revisar contratos anteriores, vigencia y dependencias del contrato editado. | Valida separación de temporales y unicidad de activo. La edición que deja sin respaldo asistencias o vacaciones devuelve error en contrato. |
| RN-06.1 | Usuario, fecha, hora y turno. En modo de marcación, identidad y clave del personal. | El servicio calcula jornada, tipo, tardanza y espera entre acciones. Una marca repetida indica que hay que actualizar la pantalla. El turno nocturno conserva la fecha de inicio. |
| RN-07.1 | Persona con contrato nombrado o permanente, días y fecha inicial. | Evalúa cupo y solapamiento. Una vacación que cruza de año debe dividirse en dos solicitudes. |
| RN-08.1 | Departamento, provincia, distrito y perímetro ordenado. | Requiere al menos tres vértices y revisa cruces, área e invasión de otra zona. |
| RN-09.1 | Rango, días, turnos, zona, vehículo, conductor y ayudantes. | El servicio propone o guarda el plan tras verificar cruces, disponibilidad y condiciones del equipo. El rango está acotado a 366 días y el lote a 5 000 programaciones. |
| RN-09.2 | Programación pendiente, valores de reemplazo y motivo cuando cambia el turno, vehículo o equipo. | Se impide cambiar de turno después de la asistencia o quitar personal que ya marcó. Un reemplazante que entra a un turno comenzado debe haber marcado en ese turno. |
| RN-09.3 | Operador con permiso, cambio vigente, estado previo compatible. | Para cambios del mismo tipo se deshace el más reciente primero. Se rechaza si la programación finalizó, si su fecha pasó o si el valor anterior ya no está disponible. |
| RN-11.1 | Token válido, programación propia, conductor y ayudantes presentes. | La antelación configurable es 30 minutos. Si alguien falta, devuelve «Todo el personal debe registrar su asistencia antes de iniciar el recorrido». |
| RN-12.1 | Recorrido propio, coordenadas, precisión opcional y momento. | Se guardan puntos desde un minuto antes del inicio hasta un minuto por delante del reloj del servidor. Cerrar exige observación. GPS y cierre se serializan usando el bloqueo del recorrido. |
| RN-13.1 | Zona activa, consulta anónima. | Se publica el estado del recorrido cuando existe. El vencimiento del turno por sí solo produce `sin_confirmacion`. |

Las reglas RN-05.1–RN-09.3 usan los permisos y rutas de sus filas principales y las mismas pruebas de la [matriz](MATRIZ_DE_TRAZABILIDAD.md). Las reglas RN-11.1–RN-13.1 se verifican en `ConductorApiTest`.
