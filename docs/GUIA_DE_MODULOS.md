# Guía de módulos

## Convenciones comunes

Todos los módulos de esta tabla requieren un administrador activo (`usertype_id` nulo) y sesión autenticada. Las rutas están en `routes/web.php`. Cada listado tiene una página HTML y una URL `/datos` JSON, con nombre `admin.{nombre}.datos`. Las antiguas llamadas AJAX a `index` siguen admitidas por compatibilidad.

Las vistas están en `resources/views/admin/{carpeta}/`; el controlador está en `app/Http/Controllers/Admin/{Controlador}.php`. Los modelos están en `app/Models/`. Los controladores coordinan el guardado. Los módulos complejos validan entradas en Requests/Admin dentro de la transacción; los catálogos sencillos conservan métodos privados y las reglas de negocio viven en servicios. `store` crea, `update` modifica y `destroy` elimina cuando el módulo lo permite. Revise cada método y sus pruebas antes de cambiar una regla.

| Función / acciones | URL base | Nombre/carpeta de vistas | Controlador | Modelo → tabla | Reglas y pruebas principales |
|---|---|---|---|---|---|
| Colores: catálogo y código hexadecimal | `/admin/colores` | colores | ColorController | ColorVehiculo → vehiclecolors | Controlador; SeguridadYValidacionesTest |
| Marcas: catálogo y logotipos | `/admin/marcas` | marcas | MarcaController | Marca → brands | Controlador; RSUOperationalWorkflowTest |
| Modelos por marca: alta, edición y baja sin vehículos | `/admin/modelos` | modelos | ModeloController | Modelo → brandmodels | `reglas`, `verificar`; NavegacionTest, RSUOperationalWorkflowTest |
| Tipos de vehículo | `/admin/tipos-vehiculo` | tiposvehiculo | TipoVehiculoController | TipoVehiculo → vehicletypes | Controlador; RSUOperationalWorkflowTest |
| Vehículos: flota e imágenes | `/admin/vehiculos` | vehiculos | VehiculoController | Vehiculo → vehicles; ImagenVehiculo → vehicleimages | Controlador; RSUOperationalWorkflowTest |
| Tipos de personal: funciones del trabajador | `/admin/tipos-personal` | tipospersonal | TipoPersonalController | TipoPersonal → usertypes | Controlador; ReglasDeNegocioTest |
| Personal: datos y contratos por persona | `/admin/personal` | personal | PersonalController | Personal → users | Controlador; ReglasDeNegocioTest, SeguridadYValidacionesTest |
| Contratos: alta, edición y cambio de estado; sin baja | `/admin/contratos` | contratos | ContratoController | Contrato → contracts | ReglasContrato; ReglasDeNegocioTest, CorreccionesDeAuditoriaTest |
| Asistencias: consulta, registro manual y marcación DNI | `/admin/asistencias` | asistencias | AsistenciaController | Asistencia → attendances | ServicioAsistencia, ResolverTurno; CorreccionesDeAuditoriaTest, Avance02RestriccionesTest |
| Vacaciones: solicitud, aprobación y rechazo | `/admin/vacaciones` | vacaciones | VacacionController | Vacacion → vacations | ReglasVacacion; ProgramacionTest, Avance02RestriccionesTest |
| Turnos: horarios diurnos/nocturnos sin cruces | `/admin/turnos` | turnos | TurnoController | Turno → schedules | Controlador y modelo; ProgramacionTest, Avance02RestriccionesTest |
| Zonas: perímetros y ubicación administrativa | `/admin/zonas` | zonas | ZonaController | Zona → zones; ZonaCoordenada → zonecoords | ReglasZona, Ubigeo; ZonasTest, Avance02RestriccionesTest |
| Feriados: catálogo y carga de calendario | `/admin/feriados` | feriados | FeriadoController | Feriado → holidays | CargadorFeriados; ProgramacionTest, Avance02RestriccionesTest |
| Usuarios: cuentas administradoras | `/admin/usuarios` | usuarios | UsuarioController | User → users | Controlador; CorreccionesDeAuditoriaTest |
| Bitácora: consulta y filtros; no editable | `/admin/bitacora` | bitacora | BitacoraController | Bitacora → audit_logs | Modelo y trait Auditable; CorreccionesDeAuditoriaTest |

Los archivos de prueba citados están en `tests/Feature/`. `NavegacionTest` recorre las páginas y endpoints de los 15 módulos; no demuestra por sí solo todas sus reglas de negocio.

## Interfaces y rutas especiales

- Inicio: `/admin`, `InicioController`, `resources/views/admin/inicio.blade.php`; pruebas `InicioTest`.
- Acceso: `/login`, Fortify, `resources/views/auth/login.blade.php`; pruebas `AuthenticationTest`, `SeguridadYValidacionesTest`.
- Perfil: `/user/profile`, Jetstream, `resources/views/profile/`; pruebas `ProfileInformationTest`, `UpdatePasswordTest`.
- Marcación: `/admin/asistencias/marcacion`; estado en `/admin/asistencias/marcacion/estado`; POST a la misma URL de marcación. Requiere el panel abierto por un administrador y la credencial del trabajador.
- Mapa: `/admin/zonas/mapa`. `ZonaController::mapa()` y JavaScript de zonas dibujan los polígonos; los mosaicos requieren red.
- Imágenes: GET/POST `/admin/vehiculos/{vehiculo}/imagenes`, en `VehiculoController`.
- Calendario: GET `/admin/feriados/cargar/consultar`, POST `/admin/feriados/cargar`.

## Ejemplo completo: cambiar Modelos

1. Localice sus rutas con `php artisan route:list --path=admin/modelos -v`.
2. Revise `ModeloController`, `Modelo`, la relación `marca` y la restricción de vehículos.
3. Edite `_form.blade.php` para campos y `resources/js/admin/modulos/` para columnas/interacciones. La tabla utiliza el gestor compartido.
4. Mantenga la forma de `fila()` si cambia la información enviada a JavaScript; actualizar solo el HTML deja desalineadas las columnas.
5. Ejecute `php artisan test --filter=NavegacionTest` y la suite completa.
6. Compruebe crear/editar, duplicados, búsqueda, segunda página y navegación real. No elimine datos de trabajo para hacer pruebas.

Para los demás módulos siga el mismo recorrido, consultando los servicios de la tabla cuando existan. No copie las reglas de Modelos sobre contratos o personal: sus restricciones son distintas.


## Entradas complejas y dependencias

- **Personal:** `PersonalRequest`; DNI, tipo, nombres/apellidos, nacimiento, correo, dirección, estado y clave inicial; teléfono, licencia y foto según reglas. Depende de tipos de personal y contratos históricos. Su clave es de marcación, no acceso al panel.
- **Contratos:** `ContratoRequest` y `ReglasContrato`; persona, tipo, inicio, fin temporal, salario de dos decimales, prueba y estado. Se conserva historial: crear/editar y cambiar estado deseado, sin eliminar. Depende de persona, vacaciones y jornadas de asistencia.
- **Asistencia:** `AsistenciaRequest` y `ServicioAsistencia`; persona al crear, fecha, hora, presente/ausente y notas. Turno, jornada, entrada/salida y tardanza se calculan; no son decisiones libres del formulario. Depende de persona, contrato, vacaciones y turnos.
- **Vacaciones:** `VacacionRequest` y `ReglasVacacion`; persona al crear, días, inicio y notas. Fin calculado; estados pendiente/aprobada/rechazada. Solo pendiente se modifica/elimina. Aprobación revalida cupo y ausencia de asistencias.
- **Vehículos:** `VehiculoRequest`; nombre, código, placa, año, capacidades con dos decimales, ocupantes y relaciones tipo/marca/modelo/color. Estado activo no implica asignación a una ruta: esa función no está implementada.
- **Galería:** `ImagenesRequest` y `GaleriaVehiculos`; `images[]`, IDs distintos `delete[]`, `profile=existing:ID` o `new:posición`, y versión. Máximo 12, 2 MB por imagen y 25 megapíxeles. La posición nueva empieza en cero.
- **Zonas:** `ZonaRequest`, `ReglasZona` y `Ubigeo`; nombre, ubicación, descripción, residuos promedio, estado y 3–200 vértices. El estado inactivo no libera el espacio para invadirlo con otra zona. Geometría aproximada, no certificación territorial.
- **Usuarios:** `UsuarioRequest` y `SeguridadAdministrativa`; nombre, correo, estado y contraseña confirmada. Panel y perfil conservan al menos un administrador activo; `ProtegeCuentaActiva` cubre Livewire. El perfil reutiliza Jetstream con un componente que refresca su versión tras guardar.

Cada escritura de un registro existente envía `version`. Una aprobación o un cambio de estado también puede quedar obsoleto; 409 solicita recarga sin sobrescribir. Las reglas de campo, estados y rangos completos están en el diagnóstico original y sus correcciones en `IMPLEMENTACION_Y_VERIFICACION.md`.
