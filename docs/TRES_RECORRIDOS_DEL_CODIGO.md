# Tres recorridos para empezar

## Antes de cambiar algo

Leer `MAPA_TECNICO_ACTUAL.md` y `IMPLEMENTACION_Y_VERIFICACION.md`. Abrir la ruta, controlador, formulario, script y prueba del módulo. La validación de navegador ayuda a la persona; el servidor siempre decide si acepta la operación. Las migraciones nuevas se prueban primero sobre una base descartable.

## 1. Un catálogo: colores

1. `routes/web.php` vincula `admin.colores.*` con `ColorController`.
2. `index` devuelve la página o sus filas JSON en `/admin/colores/datos`. `fila` define el contrato que la tabla necesita, incluida la versión.
3. `resources/views/admin/colores/_form.blade.php` contiene los campos compartidos entre crear y editar.
4. `resources/js/admin/modulos/colores.js` configura la tabla y el selector de color; las piezas comunes están en `compartido`.
5. `validado` normaliza código y aplica longitud, formato y unicidad. No hace falta un servicio adicional para estas reglas sencillas.
6. `ColorVehiculo` define tabla/relación; SQL protege unicidad y dependencias. `EscrituraDelPanel` compara la versión antes de modificar o eliminar.

Para agregar un campo, cambiar migración nueva, validación, `$fillable` si corresponde, formulario, respuesta y columna de tabla juntos. Añadir una prueba de aceptación y otra de rechazo en Negocio; no modificar archivos compilados.

## 2. Un contrato

1. El buscador de personal entrega un ID, nunca un nombre como identidad.
2. La escritura entra por `EscrituraDelPanel`: bloquea persona y registro y comprueba versión. El cierre de vencidos se ejecuta antes de la transacción de la petición para no invertir el orden de bloqueos entre personas.
3. `ContratoRequest::datos` valida forma, fechas, salario y tipo; consulta `ReglasContrato` dentro de esa operación protegida.
4. `ReglasContrato` revisa edad, estado, contrato activo, separación entre intervalos y dependencias al editar.
5. `ContratoController` guarda y forma la respuesta; `Contrato` emite bitácora y mantiene versión. El endpoint de estado recibe el estado deseado.
6. El módulo JS muestra errores de campo o conflicto y conserva el formulario. El comando diario usa la misma fila de persona y eventos individuales.

Pruebas relacionadas: `ReglasDeNegocioTest`, `SeguridadYValidacionesTest`, `PlanImplementadoTest` y escenarios MySQL de contratos/personal.

## 3. Una asistencia

1. El formulario manual usa `AsistenciaRequest`; la marcación DNI valida credenciales y límites de intentos. Ambas terminan en `ServicioAsistencia::registrar`.
2. El servicio abre transacción y bloquea primero el conjunto de turnos y después la persona; recarga estado y comprueba espera para el origen DNI.
3. `ResolverTurno` y el servicio determinan turno, jornada y entrada/salida. Se comprueban contrato, vacaciones y ausencia/presencia.
4. `Asistencia::renumerarTurno` ordena marcas, conserva el inicio histórico y registra los cambios finales en bitácora.
5. Edición y baja del panel comparten el protocolo. La baja recarga el registro antes de decidir qué turno renumerar.
6. `resources/js/admin/modulos/asistencias.js` solicita una vista previa; en edición envía `ignorar`. La vista previa orienta, pero el guardado vuelve a decidir con datos actuales.

Pruebas relacionadas: `CorreccionesDeAuditoriaTest`, `ReglasDeNegocioTest`, `PlanImplementadoTest` y escenario MySQL de doble marcación.

## Convenciones para mantenerlo comprensible

Usar español en código propio nuevo. Conservar `store/update/destroy`, nombres Laravel y columnas existentes. No agregar una capa por cada función: separar cuando haya una responsabilidad diferente o una regla compartida. Explicar en comentarios el motivo de una excepción, no traducir literalmente cada línea.

Antes de entregar: ejecutar el test del módulo, regresión completa, Pint, build Vite y actualizar guías/inventario si cambió la estructura. Un test HTTP que pasa no sustituye una prueba de dos procesos en MySQL ni una revisión de teclado en navegador.
