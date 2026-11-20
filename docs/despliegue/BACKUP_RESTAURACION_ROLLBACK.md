# Respaldo y recuperación de laboratorio

Usa un directorio de acceso restringido para el respaldo y conserva aparte la clave de aplicación y las credenciales necesarias. Guarda también los archivos persistentes de Laravel y las cargas del volumen si existen. El comando de abajo cubre MySQL.

```powershell
./scripts/demo-v3.ps1 -Accion Respaldar -Base rsu_v3_demo -MysqlDump '<ruta a mysqldump.exe>'
./scripts/demo-v3.ps1 -Accion Restaurar -Base rsu_v3_demo_restore_ensayo -Mysql '<ruta a mysql.exe>' -Respaldo '<ruta absoluta al sql>' -Sha256 '<hash del respaldo>'
```

Exporta las variables `DB_HOST`, `DB_PORT`, `DB_USERNAME` y `DB_PASSWORD` en una sesión privada. Crea antes el esquema destino vacío y habilita un usuario MySQL autorizado solo para ese esquema durante la restauración. El comando rechaza esquemas ocupados, hashes distintos y archivos que excedan `MaxRespaldoMB`. Asegura acceso exclusivo al destino durante la importación.

Tras restaurar, compara los conteos y los registros ligados al recorrido. Inicia un servidor separado contra la nueva base, comprueba salud y ejecuta el flujo de panel y APK antes de cambiar el origen. Ante fallo parcial, conserva el original y reintenta con un esquema vacío nuevo.

Antes de actualizar guarda también el commit de origen y los APK con sus hashes. Para revertir, conserva la base actual como diagnóstico, levanta el commit previo con una copia restaurada y decide cómo reconciliar las operaciones capturadas tras el respaldo antes de devolver tráfico. El tiempo observado con un fixture pequeño solo mide la operación SQL.
