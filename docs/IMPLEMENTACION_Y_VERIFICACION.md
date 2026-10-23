# Implementación del plan RSU

Fecha: 23/10/2026. Se conserva Laravel, las URLs, las tablas de negocio y el diseño. No se implementa planificación de rutas. Los cambios locales de CSS se trasladaron a las fuentes Vite; el logo y el lockfile anteriores se preservaron.

## Organización actual

- `app/Http/Controllers/Admin`: entradas HTTP y respuestas.
- `app/Http/Requests/Admin`: entradas compartidas de módulos complejos. El método `datos()` valida explícitamente dentro de la transacción del panel; no agregar reglas que consulten datos antes del bloqueo.
- `app/Services`: reglas de negocio existentes, seguridad administrativa y galería.
- `app/Http/Middleware/EscrituraDelPanel`: transacción, autorización actual y versión de edición. Adquiere conjuntos antes que persona y registro. El actor administrativo también se bloquea durante su escritura, por lo que solicitudes de una misma cuenta se serializan.
- `app/Http/Middleware/ProtegeCuentaActiva`: estado actual y protección de perfil/Livewire.
- `resources/js/admin/compartido`: peticiones, errores, presentación, buscadores, reloj, validación, tablas y descubrimiento de módulos. Se conserva `Tads` como interfaz entre piezas.
- `resources/js/admin/modulos`: una pantalla o módulo por archivo. Turnos, feriados, usuarios y bitácora ya no están mezclados con otros catálogos.
- `resources/css/admin`: panel, menú y acceso; fuentes propias únicas. `public/build` es salida de Vite y `public/vendor` conserva terceros.
- `tests/Feature/{Negocio,Seguridad,Navegacion,Arquitectura}` y `tests/Concurrencia`: pruebas por responsabilidad.

## Estado de los hallazgos

1. **H01:** las escrituras de vacaciones comparten transacción y bloqueo por persona; versiones antiguas devuelven 409. Aprobar con asistencia en el periodo devuelve 422.
2. **H02:** la espera DNI se comprueba después de bloquear y recargar persona, también cuando el servicio se invoca fuera de HTTP.
3. **H03:** las entradas del panel de personal/contratos/vacaciones/asistencias comparten protocolo. El cierre automático bloquea persona y emite eventos individuales. El servicio de asistencia recarga estado.
4. **H04:** panel y perfil protegen administradores activos; el conjunto administrativo tiene una fila estable de bloqueo. Perfil y Livewire vuelven a comprobar cuenta activa; el perfil comparte validación de versión y actualiza su versión después de cada guardado.
5. **H05:** turnos y zonas validan/escriben bajo bloqueo estable, incluso sin registros previos. Asistencia comparte bloqueo de turnos y una nueva FK impide borrar turnos usados.
6. **H06:** galería bloquea vehículo, cuenta dentro de la operación, comprueba pertenencia y selección, y actualiza versión. La baja de vehículo comparte ese bloqueo.
7. **H07:** temporales comparan ambos lados del intervalo con meses sin desbordamiento. Asistencias nocturnas utilizan jornada para revisar respaldo contractual.
8. **H08:** se auditan usuarios, cierre de contratos y renumeración; la bitácora conserva nombre histórico de actor y excluye secretos/versiones.
9. **H09:** conflictos de integridad SQL conocidos se convierten a rechazo comprensible. Los errores de infraestructura inesperados siguen siendo errores y no se silencian.
10. **H10:** registros editables tienen columna `version`; formularios y acciones envían la versión leída. El estado del contrato exige `is_active` deseado.
11. **H11:** formatos de logo alineados, inicio anterior de vacaciones editable sin cambiarlo y vista previa de asistencia recibe `ignorar`.
12. **H12:** consulta geográfica fallida muestra que no pudo verificarse, en lugar de anunciar validez.
13. **H13:** máximo 200 vértices, precisión de siete decimales, rechazo de repeticiones, contactos y retrocesos colineales. Se conserva geometría aproximada y tolerancia de 1%.
14. **H14:** archivos anteriores se limpian después del commit y los fallos se registran; una excepción al guardar conserva compensación de archivos nuevos.

Cada punto describe la implementación, no una certificación de producción. Las pruebas HTTP/reglas y las de dos procesos son evidencias diferentes; estas últimas requieren MySQL real.

## Contratos HTTP

Las ediciones, bajas y acciones sobre registros existentes requieren `version`, entero positivo obtenido del formulario o de `/datos`. La galería entrega su versión en su GET. Ausencia de versión devuelve 422; versión caducada devuelve 409 y no sobrescribe. Una baja de un registro que ya no existe devuelve 404.

`PATCH /admin/contratos/{id}/estado` exige `is_active` booleano además de versión. Se mantiene la URL y deja de alternar implícitamente. Un reintento con versión antigua recibe conflicto; el cliente consulta el resultado antes de intentar otra escritura.

Se conservan las claves JSON de negocio; se añade `version`. Bitácora acepta `draw/start/length` y devuelve `recordsTotal/recordsFiltered`; sin `draw` conserva respuesta limitada y avisa mediante `truncado`. El frontend de bitácora pagina en servidor, filtra por entidad/acción y no anuncia búsqueda u orden no implementados.

## Preparar y verificar

```powershell
composer install
npm ci
npm run build
php vendor/phpunit/phpunit/phpunit
php vendor/bin/pint --test
php artisan route:list
php scripts/generar-inventario.php
php scripts/generar-inventario.php --comprobar
```

PHP necesita `pdo_sqlite`, `pdo_mysql`, `mbstring`, `fileinfo` y `gd`. Si PHP ve un archivo pero `is_readable` devuelve falso únicamente dentro de una herramienta restringida, revisar permisos de ejecución de esa herramienta antes de reinstalar dependencias.

Para MySQL: crear **una base descartable `rsu_pruebas`**, configurar host/usuario/clave de pruebas y ejecutar `php vendor/phpunit/phpunit/phpunit -c phpunit.mysql.xml`. Esa suite elimina/recrea únicamente la base con sufijo `_pruebas` y solo se activa con `RSU_MYSQL_TESTS=1`. Nunca usar credenciales operativas. Incluye ocho escenarios en dos procesos con barrera: vacaciones, marcación, contratos, personal, administradores, turnos, zonas y galería. CI dispone de MySQL 8 separado.

## Migraciones y despliegue

Las migraciones nuevas crean tres filas estables de bloqueo, versiones de edición, identificación histórica del actor y FK de turno restringida. No reescriben migraciones antiguas ni borran datos de negocio. Una identificación de actor eliminada antes de la migración no puede reconstruirse; la pantalla lo indica.

1. Verificar un respaldo de la base y de los archivos subidos en un entorno de restauración separado.
2. Ejecutar `php artisan rsu:verificar-integridad` sobre una copia migrada. El comando solo informa identificadores y motivos; devuelve error si encuentra inconsistencias. Corregirlas expresamente, sin saneamiento automático.
3. Ejecutar las pruebas MySQL en base descartable y revisar su salida antes de aprobar el despliegue.
4. Preparar assets y desplegar backend/frontend coordinadamente, con el panel en mantenimiento durante el cambio de contrato de edición; aplicar `php artisan migrate --force` y retirar mantenimiento tras la comprobación.
5. Confirmar permisos de archivos, cache/sesiones compartidas cuando haya varias instancias, y cron de `schedule:run`. No desplegar `public/hot`.
6. Vigilar 409/422, errores SQL, tiempos de espera de bloqueos, importaciones y limpieza de archivos. Revertir código no restaura datos eliminados; inspeccionar las migraciones inversas antes de usarlas.

## Límites de verificación

La suite original ejecutó 166 pruebas sin fallos (6 omitidas, 1 incompleta). La entrega final ejecutó **192 pruebas y 1.769 aserciones tanto en SQLite como en MySQL 8.4**, sin errores ni fallos y con las mismas omisiones previas. Los ocho escenarios de dos procesos pasaron además en MySQL 8.4 con 63 aserciones, sobre una instancia separada en el puerto 33308. Se comprobaron Pint completo, compilación Vite, sintaxis de 27 fuentes JS, 175 rutas e inventario regenerado. Ambas instancias temporales se cerraron y sus bases se retiraron; las evidencias JUnit y captura quedaron en `storage/framework/testing`.

La recuperación por consola también usa el bloqueo administrativo. La prueba de baja con foto comprueba un commit real y evita que limpiar la foto vuelva a guardar una cuenta eliminada. Las pruebas de archivos ya no eliminan carpetas completas que puedan contener imágenes de trabajo.

En navegador se verificó acceso, alta/edición/baja de un color, apertura del editor Leaflet y paginación de bitácora, sobre SQLite aislado. No se completó una matriz exhaustiva de lectores de pantalla, dispositivos, cortes de red o todas las combinaciones de galerías en navegador. Las pruebas de regresión cubren HTTP y reglas, pero esos recorridos manuales adicionales no deben darse por ejecutados.

No se aplicaron migraciones sobre la base operativa ni se saneó información histórica. La concurrencia MySQL se ejecutó en una instancia descartable, no en producción; CI también la configura con MySQL 8. El respaldo/restauración operativo sigue pendiente del responsable del despliegue. Persisten límites normales: un proceso que muera entre escribir un archivo y confirmar la base puede dejar un archivo huérfano; las compensaciones de excepción no cubren la caída física del proceso.

Al probar un commit real se detectó otra limitación histórica: el rollback integral de SQLite falla al retirar `users.dni` con su índice único en la migración antigua de personal. Se conservó esa migración según el plan; la prueba de commit usa una base descartable sin ejecutar ese rollback. No usar el rollback histórico de SQLite como mecanismo de restauración. Validar las migraciones inversas sobre una copia del motor de destino y mantener respaldo restaurable.
