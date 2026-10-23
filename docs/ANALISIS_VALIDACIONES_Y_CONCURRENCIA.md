# Validaciones, concurrencia y casos límite

> Diagnóstico original previo a implementación. Para estado de H01–H14, ubicaciones actuales y pruebas, leer [Implementación y verificación](IMPLEMENTACION_Y_VERIFICACION.md). Las referencias de código del cuerpo describen el snapshot auditado.

Fecha: 23/10/2026. Fuente: código del checkout local; complemento de `MAPA_TECNICO_ACTUAL.md` e `inventario-proyecto.json`. No se modificó código funcional ni se accedió a datos reales o al contenido de `.env`.

## Alcance y lectura del informe

El sistema administra flota, personal, contratos, asistencias, vacaciones, catálogos, zonas, feriados y cuentas municipales. Se asume uso por varios administradores y quioscos concurrentes, aunque la carga real no fue medida. La programación de rutas todavía no está implementada.

Se revisaron rutas propias, controladores, servicios de negocio, modelos principales, migraciones de integridad, autenticación, formularios Blade, gestor JavaScript y flujos sensibles del frontend. Se inspeccionaron configuración, documentación y nombres/casos de pruebas. No equivale a demostrar todas las combinaciones posibles ni a una auditoría de dependencias.

Etiquetas: **actual** = defensa visible en el código; **hallazgo** = fallo o inconsistencia deducible con escenario concreto; **pendiente** = decisión o comprobación propuesta, no defecto demostrado en producción. Los hallazgos de carrera son intercalaciones posibles por lectura estática, no reproducciones con procesos reales. Las referencias indican archivo y método para que sobrevivan mejor a movimientos de líneas.

## Diagnóstico

La base tiene defensas importantes: Gate por solicitud, validación de servidor, CSRF del grupo web, claves foráneas, unicidad, reglas de negocio compartidas, transacciones en operaciones compuestas y bloqueo de la persona en asistencias y parte de vacaciones. No sería correcto afirmar que falta toda validación o toda protección concurrente.

El principal problema es la cobertura desigual de esas defensas: una transacción no impide que otro flujo que no comparte el bloqueo cambie los datos usados para decidir. Priorizar el ciclo completo de vacaciones, contrato/personal, marcación y administración de cuentas.

## Hallazgos prioritarios: corregir

### H01 — Aprobar, rechazar y eliminar vacaciones pueden competir

**Qué es:** una solicitud solo puede resolverse una vez y solo se elimina si está pendiente. Referencia: `app/Http/Controllers/Admin/VacacionController.php`, `aprobar`, `rechazar`, `destroy`, `update`.

**Problema:** aprobar bloquea la persona y refresca; rechazar refresca sin bloquear la persona ni la solicitud; eliminar comprueba el estado antes de borrar, sin transacción. Un rechazo lee pendiente, una aprobación confirma aprobada y el rechazo escribe rechazada; o eliminar lee pendiente, aprobar confirma y eliminar borra la aprobada. Una edición también puede sobrescribir fechas mientras un rechazo trabaja con datos anteriores.

**Cambio mínimo:** usar el mismo bloqueo por persona en todas las transiciones, refrescar la solicitud después del bloqueo y comprobar pendiente dentro de la transacción. Alternativa para la transición simple: actualización condicional por `id` y `status=pendiente`, comprobando filas afectadas y manteniendo coherencia con las demás operaciones.

**Si se omite:** se pierde la resolución o desaparecen vacaciones aprobadas. Prueba: aprobar/rechazar, aprobar/eliminar y editar/rechazar con dos conexiones y barrera antes de escribir.

### H02 — Dos marcaciones simultáneas pueden convertirse en entrada y salida inmediatas

**Qué es:** existe una espera de 60 segundos configurable. Referencia: `AsistenciaController::marcar`, `ServicioAsistencia::segundosParaVolverAMarcar` y `registrar`.

**Problema:** ambas peticiones consultan la espera antes de que ninguna guarde. Dentro de `registrar` se serializan y la segunda ve una entrada, por lo que puede guardar una salida inmediatamente. El índice único no lo evita: los tipos son diferentes.

**Cambio mínimo:** volver a comprobar la espera para el origen marcación dentro de la transacción y después del bloqueo por persona. Mantener la edición administrativa fuera de esta espera salvo decisión expresa.

**Si se omite:** un doble envío o dos quioscos completan falsamente la jornada. Prueba: enviar dos solicitudes con la misma persona y comprobar una sola fila y un rechazo por espera.

### H03 — Contratos y estado del personal no comparten el bloqueo de sus dependientes

**Qué es:** vacaciones y asistencia dependen del estado y contrato. Referencias: `ContratoController::{store,update,cambiarEstado}`, `PersonalController::{validado,update}`, `ServicioAsistencia::registrar`.

**Problema:** cambiar contrato revisa dependientes sin bloquear la persona. Puede comprobar que no hay vacaciones, otra solicitud crearlas/aprobarlas y después desactivar el contrato. Crear contrato y desactivar personal también pueden aprobar sus comprobaciones por separado. Además `registrar` comprueba el estado antes de bloquear y descarta el modelo obtenido por el bloqueo, conservando el objeto anterior.

**Cambio mínimo:** que todos los escritores de estas reglas bloqueen la misma persona, recarguen su estado y revisen dependientes dentro de esa transacción. Incluir cierre automático de contratos en el diseño; evitar una solución que solo proteja el formulario.

**Si se omite:** personal inactivo con contrato nuevo, o vacaciones/asistencias aceptadas con premisas caducadas. Prueba: crear contrato/inactivar, desactivar contrato/solicitar vacaciones, marcar/inactivar.

### H04 — El último administrador activo no está protegido en todos los caminos

**Qué es:** debe conservarse acceso administrativo. Referencias: `UsuarioController::motivoParaNoDesactivar`, `motivoParaNoEliminar`; `app/Actions/Jetstream/DeleteUser.php`.

**Problema:** desde perfil se cuentan administradores totales, incluidos inactivos. Si A es el único activo y B está inactivo, A puede eliminarse porque hay dos cuentas. Además A y B activos pueden desactivar mutuamente sus cuentas con peticiones concurrentes: cada uno encuentra al otro activo antes de escribir.

**Cambio mínimo:** unificar la regla de administrador activo en panel y perfil y serializar las operaciones con un recurso estable común o bloqueo determinista del conjunto; revalidar después de bloquear. No basta con bloquear solo al usuario objetivo.

**Si se omite:** quedan cero administradores activos. Pruebas: único activo más uno inactivo; desactivaciones y autoeliminaciones concurrentes.

### H05 — Dos turnos o dos zonas nuevos pueden cruzarse

**Qué es:** el conjunto no debe admitir horarios superpuestos ni invasiones superiores a la tolerancia. Referencias: `TurnoController::validado/validarSinCruces`, `ZonaController::validado/store/update`, `ReglasZona::zonaInvadida`.

**Problema:** dos solicitudes con nombres diferentes validan contra el conjunto anterior y ambas escriben. Las zonas usan transacción para guardar vértices, pero su validación ocurre antes. Un índice de nombre único no protege intervalos ni polígonos.

**Cambio mínimo:** serializar validación y escritura con un bloqueo estable por conjunto afectado. Debe funcionar también si todavía no existen filas; bloquear una consulta vacía no es una estrategia suficiente por sí sola. Revalidar dentro de la sección protegida.

**Si se omite:** la elección de turno resulta ambigua o se guardan zonas que el sistema normalmente rechazaría. Pruebas: crear dos turnos cruzados y dos zonas invasoras en paralelo, también desde base vacía.

### H06 — El límite de imágenes puede excederse entre dos cargas

**Qué es:** máximo 12 imágenes por vehículo y una de perfil cuando existen imágenes. Referencia: `VehiculoController::guardarImagenes/definirPerfil`.

**Problema:** el conteo se realiza fuera de la transacción y no se bloquea el vehículo. Con 11 imágenes, dos cargas de una imagen pasan y dejan 13. Dos selecciones de perfil o eliminar vehículo mientras se sube también requieren coordinación.

**Cambio mínimo:** bloquear el vehículo, contar y validar selección/bajas dentro de la transacción. Mantener la compensación actual que elimina archivos nuevos si falla el guardado; comprobar el resultado de eliminar archivos.

**Si se omite:** se incumple el límite y pueden quedar selecciones obsoletas o archivos sin registro. Pruebas: 11+1+1 concurrentes; cambiar perfil mientras se elimina esa imagen; subir mientras se borra vehículo.

### H07 — Insertar contratos históricos evade la separación frente al contrato siguiente

**Qué es:** se exige una pausa configurable entre contratos temporales. Referencia: `ReglasContrato::validarPlazoEntreTemporales`.

**Problema:** solo busca contratos cuyo inicio es anterior al nuevo inicio. Si ya existe un temporal de septiembre a octubre, insertar después uno inactivo de agosto a mediados de septiembre no compara el nuevo fin con el inicio de septiembre. Ambos pueden quedar superpuestos o sin los dos meses de separación.

**Cambio mínimo:** validar ambos vecinos del intervalo, también al editar y para contratos inactivos históricos según la política actual. Precisar cómo se suman meses desde fines de mes.

**Si se omite:** el historial admite combinaciones que dependen del orden de carga. Prueba: cargar los mismos contratos en ambos órdenes y esperar idéntico resultado.

### H08 — Los cambios de seguridad y varias escrituras masivas no quedan en bitácora

**Qué es:** trazabilidad de quién cambió qué. Referencias: `app/Models/User.php`, `Concerns/Auditable.php`, `Contrato::cerrarVencidos`, `Asistencia::renumerarTurno` y `BitacoraController::MODELOS`.

**Problema:** `User` no usa `Auditable`; crear, desactivar o borrar administradores no genera esos eventos. El cierre masivo de contratos y las actualizaciones de renumeración usan consultas de actualización, sin los eventos individuales usados por el trait. Registrar una asistencia ocurre antes de su renumeración, por lo que el evento de alta puede tener tipo nulo y no reflejar el resultado final.

**Cambio mínimo:** registrar explícitamente las operaciones de seguridad y los cambios masivos relevantes, dentro de la transacción, excluyendo credenciales. Extender filtros de consulta si se incorporan esos eventos.

**Si se omite:** no se puede reconstruir fielmente una pérdida de acceso o ciertos cambios históricos. Pruebas: alta/baja de administrador, cierre programado y tipo final de una marcación en la bitácora.

## Hallazgos adicionales: resolver en el siguiente ciclo

### H09 — Conflictos SQL pueden llegar como error genérico

**Qué es:** dos altas con DNI, correo, placa, nombre o contrato activo iguales pueden superar `unique` al mismo tiempo. Referencias: validadores de controladores, migraciones de unicidad y `bootstrap/app.php`.

**Problema:** las restricciones protegen los datos, pero la mayoría de controladores no convierte el conflicto final a un error de campo; `ServicioAsistencia` sí captura unicidad. Comprobar también claves foráneas cuando se borra un catálogo usado durante la operación.

**Cambio mínimo:** traducir restricciones conocidas a respuestas comprensibles sin ocultar errores inesperados. **Si se omite:** operación segura en la base, pero mensaje 500 o genérico e incertidumbre para el usuario. Probar dos altas iguales y crear hijo mientras se borra padre.

### H10 — Ediciones obsoletas sobrescriben cambios y acciones de estado se repiten

**Qué es:** dos administradores abren el mismo formulario. Referencias: métodos `update` de CRUD y `ContratoController::cambiarEstado`.

**Problema:** no se envía versión del registro para detectar que otro ya lo cambió. El estado del contrato se invierte con cada solicitud; un reintento posterior al éxito puede revertirlo. Deshabilitar el botón ayuda en una pestaña, pero no protege el servidor.

**Cambio mínimo:** control de versión mediante `updated_at` o contador, actualización condicional y aviso de conflicto; enviar el estado deseado, no una orden de alternarlo. **Si se omite:** se pierde trabajo o se cambia el estado dos veces. Pruebas: guardar dos versiones y repetir exactamente una petición de estado.

### H11 — Ayudas del frontend discrepan del backend

**Qué es:** requisitos visibles deben corresponder a los aceptados. Referencias: `resources/views/admin/marcas/_form.blade.php` y `MarcaController::validado`; `vacaciones/_form.blade.php` y `ReglasVacacion::revisar`; `public/js/tads/asistencias.js` y `AsistenciaController::vistaPrevia`.

**Problema:** el selector y mensaje de logo ofrecen SVG aunque el servidor lo rechaza. La fecha mínima de vacaciones siempre es hoy aunque backend permite editar sin cambiar un inicio pasado. La vista previa de asistencia al editar no envía `ignorar`, aunque backend lo admite; una jornada completa puede aparecer completa al previsualizar la edición de una de sus propias marcas.

**Cambio mínimo:** alinear `accept` y mensaje; conservar el mínimo aplicable a la edición; enviar el ID al previsualizar. **Si se omite:** formularios bloqueados o avisos equivocados. Pruebas de navegador con esos tres casos.

### H12 — Fallar una comprobación geográfica se presenta como ausencia de problema

**Qué es:** validación en vivo del perímetro. Referencia: `public/js/tads/zonas.js`, `revisarPerimetro`.

**Problema:** si `peticion` falla, `problema` se establece a nulo, se oculta el aviso y se pinta el perímetro con estilo normal. El guardado final sí vuelve a validar.

**Cambio mínimo:** distinguir validando, válido, inválido y no se pudo verificar. **Si se omite:** falsa sensación de validez durante una caída de red. Prueba: interrumpir la consulta de verificación y confirmar mensaje explícito, sin cambiar la validación final.

### H13 — Geometría sin límite de vértices y con aproximaciones

**Qué es:** validación de zonas en `ZonaController::{validado,verificar}` y `ReglasZona::{seCruza,seIntersectan,fraccionEnComun}`.

**Problema:** no hay máximo de vértices; la búsqueda de cruces crece con el cuadrado de los puntos y el muestreo de solape recorre una cuadrícula por zona. Un perímetro enorme consume mucho trabajo. La intersección solo detecta cruces estrictos: un contorno con un lado que vuelve por el mismo segmento colineal o toca otro vértice puede pasar esa comprobación. La pertenencia territorial se basa en rectángulos y el solape es aproximado.

**Cambio mínimo:** fijar un máximo razonable según uso real; rechazar vértices repetidos no vecinos, contactos y tramos colineales superpuestos con casos de prueba. **Si se omite:** latencia alta o figuras inválidas aceptadas. No presentar las cajas como validación de frontera administrativa exacta; usar límites oficiales solo si el negocio requiere esa precisión.

### H14 — Borrar archivos antes de confirmar la baja puede dejar registros sin imagen

**Qué es:** eliminar marca o personal. Referencias: `MarcaController::destroy`, `PersonalController::destroy`, `RespondeAjax::borrarArchivo`.

**Problema:** primero se borra el archivo y luego la fila. Si otra petición crea una dependencia después del conteo, el borrado SQL puede fallar y el registro conserva una ruta cuyo archivo ya no existe. `@unlink` tampoco comunica un fallo de limpieza. Vehículos ya borran la fila antes del archivo.

**Cambio mínimo:** confirmar el borrado de datos antes de limpiar archivos, registrar fallos de limpieza y coordinar dependencias. **Si se omite:** imágenes rotas o archivos huérfanos. Prueba: provocar fallo SQL tras el conteo y verificar que el archivo original siga existiendo.

## Catálogo de validaciones actuales y propuestas por módulo

### Reglas transversales

- **Frontend actual:** HTML `required`, tipos, patrones y límites; `Tads.Gestor` llama `checkValidity/reportValidity`, deshabilita guardar, pinta errores 422 y enfoca el primer campo; buscadores exigen elegir un ID y admiten teclado. Hay escape de texto al generar HTML, avisos de sesión 401/419 y permiso 403. Las búsquedas compartidas descartan respuestas anteriores mediante contador; asistencia cancela con `AbortController`.
- **Backend actual:** Gate exige administrador activo en `/admin`; el personal operativo no inicia sesión. Se usan datos validados, `Rule::in`, consultas parametrizadas, listas permitidas para orden de tablas y CSRF web. No hay registro público. La verificación de correo está desactivada y `User` no implementa `MustVerifyEmail`; el middleware `verified` no significa que hoy se obligue a verificar correos.
- **Propuesto:** validar forma antes de convertir valores: arreglos donde se espera string, IDs enteros positivos, booleanos explícitos en vez de interpretar silenciosamente valores arbitrarios; rechazar tamaños y estructuras excesivas, propiedades inesperadas en objetos anidados y controles Unicode no permitidos. Normalizar antes de unicidad, conservar DNI como texto, fijar política de mayúsculas/tildes/espacios y comprobarla en la colación real.
- **Propuesto:** acordar formato estricto `Y-m-d`, precisión decimal (salarios/capacidades), redondeo y unidades. No confiar en `step` del navegador: enviar `salary=100.999` por HTTP debe tener resultado explícito, no redondeo accidental. Comprobar valores vacíos, nulos, cero, negativos, notación científica, límites exactos y exceso de longitud.
- **Experiencia propuesta:** error por campo más resumen accesible con asociación `aria-describedby`/`aria-invalid` y anuncio de errores; conservar datos ante 422/red; distinguir 404, 409, 413, 429, 502 y 503; avisar si el guardado tuvo resultado desconocido antes de reintentar. Confirmar mediante navegador y lector de pantalla; no se verificó accesibilidad completa.

### Colores, marcas, modelos y tipos

- **Actual:** colores con nombre único <=100, código hexadecimal `#RRGGBB` único y descripción <=500; marcas con nombre único <=100, descripción <=500 y logo MIME permitido <=2 MB; modelos con marca existente, nombre <=100 y código <=30 únicos dentro de la marca, código normalizado y patrón alfanumérico/guiones. No se mueve de marca un modelo que tiene vehículos.
- **Actual:** tipos de vehículo con nombre único <=100 y descripción <=500; tipos de personal con nombre normalizado único 3–100, letras/espacios/apóstrofes/puntos/guiones y descripción <=255. Las funciones del sistema no se eliminan y se reconocen por código interno, no nombre; los catálogos usados se protegen frente a baja.
- **Propuesto:** verificar cadenas de solo puntuación si deben representar nombres reales; mensaje de nombres dice solo letras/espacios aunque regex admite más. Acordar duplicados por tildes y mayúsculas. No agregar restricciones arbitrarias a marcas o nombres humanos sin necesidad.
- **Casos:** catálogo vacío; borrado entre selección y guardado; modelo perteneciente a otra marca; marca cambiada mientras el formulario permanece abierto; renombrar Conductor sin alterar obligatoriedad de licencia; mismas claves en marcas distintas.

### Vehículos e imágenes

- **Actual:** código normalizado único <=50 y patrón; placa normalizada única con tres presentaciones permitidas; año 1990 a siguiente año; carga y combustible >0, compactación >=0 (máximo 999999), ocupantes 1–10; nombre <=100 y descripción <=500; relaciones existentes y modelo de la marca elegida. MySQL añade FK compuesta modelo/marca.
- **Actual:** imágenes JPEG/JPG/PNG/GIF <=2 MB; IDs de baja se consultan desde la relación del vehículo, perfil existente también; máximo 12 y compensación de archivos ante excepción. Si el perfil solicitado no existe, se elige uno de respaldo en vez de rechazarlo.
- **Propuesto:** limitar `images` y `delete` en número además del conteo final; IDs distintos, pertenencia y perfil nuevo con índice válido; mensajes sobre selección caducada. Validar dimensiones/píxeles y archivos corruptos, capacidad total del request, errores parciales de almacenamiento. Definir si compactación tiene relación con carga según unidades y modelo real, sin imponerla por intuición.
- **Casos:** quitar todas las imágenes; quitar la de perfil; subir GIF animado; archivo vacío, truncado o MIME distinto de extensión; nombres iguales; disco lleno; proceso cae entre mover archivo y commit; servidor rechaza por `post_max_size`; dos pestañas de galería. Placas `ABC123` y `ABC-123` son cadenas distintas: confirmar si deben representar el mismo vehículo antes de normalizar separadores.

### Personal

- **Actual:** DNI 8 dígitos único; tipo existente; nombres/apellidos <=100 y patrón de letras/puntuación admitida; nacimiento posterior a 01/01/1940 y edad >=18; teléfono opcional de 9 dígitos empezando por 9; correo único <=150; dirección 10–255; licencia única letra+8 dígitos obligatoria para conductor y eliminada para otras funciones; clave de marcación 6–60; foto JPG/PNG/WEBP <=2 MB. No se elimina con contratos/asistencias/vacaciones ni se inactiva con contrato vigente.
- **Propuesto:** revisar edición de nacimiento contra contratos históricos existentes: cambiar nacimiento puede volver inválida la edad al iniciar contratos; decidir conservación de licencia al cambiar de función (hoy se borra); diferenciar contrato activo futuro y vigente: hoy la inactivación solo consulta vigente. Guardar teléfono/DNI con ceros como texto.
- **Casos:** cumple 18 hoy; 29 de febrero; una sola parte del apellido o apóstrofes; teléfono vacío; cambio de correo por uno usado por administrador; clave vacía al editar conserva la anterior; seleccionar tipo eliminado; persona activa sin contrato no implica disponible para trabajo.

### Contratos

- **Actual:** persona operativa existente y activa; tipo permitido; inicio posterior a 01/01/2000 y hasta un año adelante; fin hasta diez años adelante; temporal exige fin posterior al inicio, otros tipos reciben fin nulo; salario >0 hasta 99999.99; prueba opcional 1–12 meses; edad mínima al inicio; un solo contrato activo, incluso si empieza en el futuro; índice único generado refuerza esa regla. No se cambia persona al editar ni se elimina el historial.
- **Actual:** pausa entre temporales respecto del anterior; control de vacaciones dependientes y asistencias fuera de nuevas fechas; cierre de vencidos por comando diario y algunos accesos al panel.
- **Propuesto:** revisar ambos extremos de temporales (H07); fecha de contrato frente a `shift_date` de salidas nocturnas: `revisarDependientes` usa `date`, mientras asistencia mide ciertas salidas por jornada. Decidir si cambiar sin variar fechas debe revisar toda la historia; si prueba excede contrato corto; si fin mismo día es permitido (actualmente PHP lo rechaza para temporales aunque CHECK permite igualdad).
- **Casos:** contrato por iniciar ocupa el único activo; vencimiento hoy sigue vigente; medianoche y cierre diario; reactivar vencido; desactivar con vacaciones; introducir históricos después de asistencias; sumar dos meses a 31/12 o 31/01. Definir estas reglas con negocio, no asumir requisitos laborales legales.

### Asistencias y marcación

- **Actual:** registro manual valida persona operativa, fecha no futura, hora HH:MM, estado presente/ausente y notas <=500. Servicio exige activo, contrato y ausencia de vacaciones, coherencia del día y máximo entrada/salida por turno. Momento admite un minuto de margen futuro en servicio. Transacción bloquea persona; índice único persona/turno/jornada/tipo. Renumeración recalcula orden y conserva inicio del turno como snapshot.
- **Actual:** anticipación de 30 min, tolerancia 10 min y gracia de salida 180 min; turno nocturno usa día de inicio; salida mide contrato/vacaciones con esa jornada. DNI normaliza a dígitos, clave <=60, mensaje uniforme y hash ficticio para inexistentes, cinco intentos por IP+DNI, espera 60 s previa al registro. Quiosco usa hora del servidor.
- **Propuesto:** H02 y H03; validar estado también al editar según política (la edición no exige activo como el alta); refrescar asistencia al eliminar después de bloquear: hoy usa turno/jornada del objeto cargado antes de esperar, que otra edición pudo cambiar, dejando sin renumerar el turno correcto. Mantener una regla común para fecha/jornada también en contratos y ausencias nocturnas.
- **Casos:** entrada sin salida previa; salida olvidada después de gracia; mismo minuto con IDs diferentes; registro retroactivo antes de otra marca; cambiar presencia a ausencia y viceversa; mover de día/turno; borrar entrada hace que la salida restante pase a entrada; ausencia calendario de madrugada frente a salida de jornada anterior; no hay turno; turno completo; edición del fin del turno mientras hay una entrada abierta (solo inicio está congelado); más de un turno diario por persona.
- **Filtros:** asistencias pagina en servidor con tamaño 1–100 y lista de columnas; compatibilidad limita a 2000. Rango invertido se intercambia, mientras otros módulos lo rechazan. `search.value` no tiene el mismo máximo explícito que `buscar`: validar también entradas anidadas y acordar comportamiento uniforme del rango.

### Vacaciones

- **Actual:** personal, días enteros 1–30, inicio válido y notas <=500; fin calculado inclusivo, días corridos; personal activo con contrato nombrado/permanente vigente al inicio; no cruza pendientes/aprobadas; solo aprobadas consumen cupo del año de inicio. Crear/editar/aprobar bloquean persona. Editar conserva persona y solo admite pendiente. Aprobar revalida derecho, cupo y que no haya terminado.
- **Propuesto:** decidir si se puede aprobar un periodo que ya empezó (hoy sí, si no terminó); consultar asistencias existentes antes de aprobar: es posible marcar días recientes y después aprobar una solicitud pendiente que ya había empezado, generando coexistencia. No imponer borrar la historia sin procedimiento de corrección. Decidir distribución del cupo entre años y antigüedad; hoy se carga todo al año inicial. Buscar personal informa disponibilidad de hoy/año actual, no necesariamente el inicio futuro elegido.
- **Casos:** 31/12 + 30 días; año bisiesto; 1 día; dos solicitudes pendientes que suman más del cupo aprobado disponible; rechazadas liberan fechas; aprobación tras perder derecho; solicitud pendiente con inicio pasado; datos de cupo cambian entre abrir y guardar; vacaciones aprobadas no tienen camino de cancelación implementado.

### Turnos

- **Actual:** nombre único <=50; horas HH:MM distintas; duración 1–12 h incluso nocturna; intervalos sin superposición; extremos contiguos permitidos; no se elimina con asistencias; se informan huecos de cobertura.
- **Propuesto:** H05; coordinar eliminar con nueva asistencia: FK actual de turno usa `nullOnDelete`, por lo que un borrado que pasó el conteo podría quitar el turno de una asistencia creada después. Definir edición de turnos usados y efecto en marcas abiertas; preservar historial requerido sin alterar el snapshot ya existente.
- **Casos:** 23:00–05:00; termina justo cuando empieza otro; 00:00–00:00; exactamente una/doce horas; huecos y ausencia total de turnos; adelanto de entrada cerca de medianoche.

### Zonas y ubigeo

- **Actual:** nombre único <=100; descripción <=500; residuos opcionales 0–99999999.99; estado booleano requerido; cascada departamento/provincia/distrito validada desde BD con respaldo de configuración si no hay filas; >=3 puntos numéricos y rangos globales, cajas de Perú/departamento, rechazo de duplicados consecutivos, cruces estrictos, colinealidad total; área 100 m²–150 km²; solape aproximado permitido hasta 1% de la zona menor incluyendo inactivas. Coordenadas guardadas a siete decimales, ordenadas y reemplazadas en transacción.
- **Propuesto:** H05/H12/H13; repetir geometría sobre precisión que realmente se almacena: redondear puede colapsar puntos casi iguales. Validar máximos de nombres de ubicación también en el guardado y límites de payload. Distinguir falla de tablas de ubigeo de catálogo vacío: respaldo actual requiere que la consulta pueda ejecutarse.
- **Casos:** borde compartido; polígono cóncavo; mismo primer/último punto repetido; puntos colineales, no consecutivos repetidos o que se tocan; inversión latitud/longitud; coma decimal; zona inactiva que bloquea una nueva; punto dentro de caja y fuera del departamento real; mapa/buscador sin conexión; mosaicos bloqueados pero coordenadas disponibles.

### Feriados

- **Actual:** fecha única 2020 a diez años adelante; descripción manual 3–150; estado requerido; filtros de fechas/estado. Importación máximo 60 elementos, fecha estricta y descripción <=150. Guardado por fecha con `firstOrCreate` respeta existentes incluso inactivos; fuente remota con timeout y calendario local de respaldo.
- **Propuesto:** descripción de lote debe respetar mínimo 3 igual que manual; fechas duplicadas del lote se colapsan con `pluck`, decidir si informar/rechazar; validar también estructura, fecha/año y longitud de los elementos remotos antes de guardar, porque esa fuente no pasa por el validador HTTP del lote. Definir atomicidad de importación completa o resultado parcial recuperable.
- **Casos:** importar dos veces; una fecha ya desactivada no se reactiva; API devuelve JSON mal formado, lista parcial o datos de otro año; guardado falla a mitad del lote; consulta un año y guarda después de Año Nuevo; descripción vacía por espacios. La vigencia oficial del calendario local no se verificó con fuentes externas.

### Usuarios, perfil, acceso y bitácora

- **Actual:** administradores identificados por `usertype_id` nulo; nombre 3–100 y correo único <=150 normalizado; contraseña confirmada <=72 y mínimo 8 con mayúsculas/minúsculas/números; clave vacía al editar conserva actual; panel impide desactivarse/borrarse a sí mismo. Perfil usa validación independiente: nombre/correo hasta 255, sin la misma normalización explícita. Recuperación solo se habilita con mailer distinto de log; no envía recuperación al personal. 2FA y passkeys configuradas; API/términos/equipos/fotos de perfil deshabilitados como features de Jetstream.
- **Actual:** bitácora omite secretos, consulta hasta 1000 eventos con filtros permitidos; actor puede quedar nulo al eliminarse. Una fila muestra “Sistema” si falta actor, lo que también puede corresponder a un usuario eliminado.
- **Propuesto:** H04/H08; unificar reglas de identidad entre perfil, altas y consola sin debilitar las contraseñas; al cambiar correo definir verificación y confianza actuales; revisar límite en bytes del algoritmo de hash frente al límite en caracteres y uniformidad con cambio/reset de clave. Confirmar passkeys, recuperación, revocación de sesiones y la Gate con usuario desactivado en flujos de perfil/Livewire, no solo `/admin`.
- **Casos:** único activo con otros inactivos; administrador modifica a otro durante una operación; sesión caducada en modal; contraseña copiada con espacios; usuario cambia correo y vuelve a login; nombre cambiado no debe borrar actor histórico; más de 1000 eventos requieren paginación o aviso de límite; búsqueda de cuentas no debe filtrar contraseñas/tokens/secretos.

## Protocolo mínimo de concurrencia para una implementación posterior

1. Definir invariantes por recurso: persona (estado, contrato, vacaciones, asistencia), vehículo (galería/perfil), conjunto de turnos, conjunto de zonas y acceso administrativo.
2. Mantener un orden de bloqueo determinista; una operación que toque varias personas debe bloquear IDs en orden. No mezclar diferentes locks para una misma regla.
3. Abrir transacción, bloquear recurso estable, recargar registros, validar reglas dependientes y escribir; confirmar antes de limpiar archivos o responder éxito. No retener transacciones durante geocodificación o descarga remota.
4. Conservar índices únicos y FKs como última defensa. Una consulta de existencia previa sirve para explicar errores, no para garantizar ausencia de carrera. Las claves únicas con columnas nulas no sustituyen todas las reglas de ausencias o estados.
5. Para ediciones humanas, detectar versión anterior y ofrecer recarga/comparación sin perder el formulario. Para reintentos, usar operaciones con estado deseado y clave de idempotencia donde haya efectos duplicables; no introducir un framework nuevo para CRUD triviales.
6. Traducir conflicto esperado a error comprensible (422 o 409 según contrato acordado), 429 con espera y error de infraestructura sin información interna. Aplicar reintentos acotados de deadlock solo sobre operaciones reejecutables; no repetir movimientos de archivos sin compensación.
7. En despliegue con varios procesos, comprobar que cache, sesiones y límites son compartidos; documentar motor, aislamiento, timeout y scheduler. `withoutOverlapping` del comando no bloquea automáticamente los cambios HTTP del mismo dominio.

## Plan de pruebas priorizado y criterios de aceptación

### P0: integridad y acceso

- H01: tres combinaciones de transición concurrente; solo un resultado válido, ninguna aprobada borrada y cupo consistente.
- H02: dos marcas con credenciales válidas y tiempo congelado; una entrada, cero salidas, segundo envío con espera.
- H03: contrato/personal/vacaciones/marcación en paralelo; al confirmar no queda ninguna operación nueva basada en estado invalidado por otra.
- H04: no debe existir secuencia permitida de baja/desactivación que deje cero activos; probar también perfil y consola de recuperación.
- H05: nombres distintos no deben permitir intervalos o perímetros incompatibles, incluso tabla vacía.
- H06: conteo <=12; si hay imágenes, un perfil; archivos referenciados existen tras éxito/fallo.

### P1: historial, paridad y errores

- H07–H14 con casos descritos; verificar índices y restricciones reales de MySQL/MariaDB con migraciones aplicadas.
- Cada campo: mínimo-1/mínimo/máximo/máximo+1; vacío/nulo/solo espacios; tipos incorrectos; ID inexistente y de otra entidad; relaciones borradas después de seleccionar; request HTTP sin frontend.
- Fechas congeladas en medianoche Lima, 29/02, 31/12, fin/inicio de turno, tolerancia y gracia con un minuto o segundo antes/después.
- Navegador: modal, Enter repetido, dos pestañas, atrás/adelante, error de red después de commit, sesión vencida, 422 conservando datos, 413 y 429, teclado/foco y avisos accesibles.
- Importaciones: caída en elemento intermedio, reanudación y repetición; no duplicar ni declarar éxito total si hubo resultado parcial.

### Cobertura existente y límites

Las suites `ReglasDeNegocioTest`, `Avance02RestriccionesTest`, `ZonasTest`, `CorreccionesDeAuditoriaTest`, `SeguridadYValidacionesTest`, `ProgramacionTest` y `RSUOperationalWorkflowTest` contienen casos de negocio/HTTP. `RSUArchitectureTest`, `AdminComponentsTest` y `NavegacionTest` revisan contratos estructurales y navegación. Las pruebas de Jetstream/Fortify cubren acceso/perfil/contraseñas/2FA y features opcionales; su presencia no demuestra que todas estén habilitadas.

`phpunit.xml` usa SQLite en memoria y cache/sesión array. La migración `2026_10_19_100005_database_integrity_constraints.php` omite en SQLite FKs compuestas, cambios RESTRICT y CHECKs. Esa suite no demuestra bloqueo de filas, aislamiento, deadlocks ni integridad específica del motor de producción. Las carreras necesitan dos conexiones/procesos reales con barreras y base descartable MySQL/MariaDB, sin ejecutar contra datos operativos.

Verificación intentada: `php artisan test --compact` se detuvo antes de ejecutar pruebas: `Cannot open bootstrap script ... vendor/autoload.php`. No se instalaron dependencias ni se modificó entorno para esta revisión. No se realizó prueba de navegador, carga, red externa, backup/restauración, configuración efectiva del servidor, datos existentes, secretos ni revisión completa de terceros. No hay resultados de tests aprobados atribuibles a este análisis.

## Decisiones que debe conservar el siguiente trabajo

Antes de implementar, confirmar: cupo de vacaciones que cruza años; aprobación retrospectiva y conflicto con asistencia; política de históricos; cambios de función/licencia; equivalencia de placas; decimales/unidades; precisión territorial; días de cobertura continua de turnos; corrección/cancelación de aprobaciones; retención de bitácora e imágenes; permisos diferenciados si se necesitan. Estas son decisiones pendientes, no nuevas funcionalidades ya autorizadas.

Veredicto: conservar las defensas existentes y completar primero H01–H06 y H04 por pérdida de acceso. No hace falta sustituir la arquitectura; hay que aplicar las mismas reglas y bloqueos a todos sus caminos de escritura.
