# Laboratorio V3

Conserva una base MySQL separada cuyo nombre empiece por `rsu_v3_demo`. Usa un usuario de MySQL con permisos limitados a esa base. Mantén el puerto de MySQL accesible solo desde la máquina del servidor.

Con Composer y npm instalados, ejecuta `composer install`, `npm ci` y `npm run build`. Genera la clave de aplicación en un `.env` local. Define `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` y `RSU_DEMO_PASSWORD` en la terminal de prueba. Ejecuta `scripts/demo-v3.ps1 -Accion Preparar` y luego `scripts/demo-v3.ps1 -Accion Iniciar -Php <ruta-a-php.exe>`. El perfil usado aquí escucha en 8104. Para restaurar un ensayo, crea una base de demo con otro sufijo, concede acceso a esa base y usa el parámetro `-Base`. El script ofrece respaldo, parada y asistencia completa con acciones separadas.

Los APK de laboratorio permiten guardar la dirección desde el acceso y comprueban `/api/v1/salud`. El emulador Android usa `http://10.0.2.2:8104`. Para Wi-Fi en ensayo privado, prepara cuentas con claves propias, revisa y reemplaza credenciales de las cuentas semilla del repositorio, y enlaza el servidor únicamente a la IP privada del adaptador con `-Escucha`. Configura esa IP en el APK. El perfil de distribución omite `RSU_MOBILE_LAB` y usa un único origen HTTPS. El servidor PHP integrado es para laboratorio.

## Conductor

1. Ingresa con el correo y la clave ficticia facilitados por quien opere el laboratorio. Revisa **Mis Programaciones** y abre el turno de hoy.
2. Comprueba la asistencia del equipo. Con todos presentes, confirma el inicio y concede ubicación y el permiso de segundo plano cuando Android lo solicite.
3. Abre **Recorrido en curso** para ver el mapa y los puntos confirmados. Si cae la red, la cola local conserva los puntos capturados hasta que el servidor los confirme.
4. En **Finalizar recorrido**, escribe de 3 a 500 caracteres y confirma. Si no hay conexión, el intento queda sin confirmar y se conserva el recorrido para reintentar. Consulta **Historial** para ver los puntos y la observación.

## Ciudadano

1. Abre **Crear cuenta** y registra DNI, nombre, apellidos, correo y contraseña. Elige una zona manualmente o solicita una lectura de ubicación y confirma el resultado.
2. En **Mis Programaciones**, consulta el mes y abre el detalle. En **Ver recorrido en mapa**, lee el estado y la hora de la última ubicación.
3. Si el recorrido terminó, elige de una a cinco estrellas y escribe un comentario opcional. Consulta tu voto y el promedio en **Historial**. En **Perfil** puedes cambiar de zona o cerrar sesión.

## GPS, mapa y seguridad

La app del conductor solicita una lectura nominal cada cinco segundos y usa tarea de ubicación, servicio foreground Android y cola SQLite, sujetos a permisos y al sistema operativo. Las operaciones GPS tienen UUID y los reenvíos se deduplican en la base del servidor. Antes de cerrar, se sincronizan puntos válidos y las lecturas rechazadas por quedar fuera de la ventana se conservan por separado para revisión, con un aviso en la app. Se mantiene pendiente un protocolo durable para confirmar un cierre solicitado sin conexión. [Ubicación en Expo SDK 54](https://docs.expo.dev/versions/v54.0.0/sdk/location/).

El mapa vectorial se calcula a partir de datos geográficos persistidos. El área sombreada representa el límite de zona. Se distingue la trayectoria observada, su última posición y la ubicación propia cuando se autoriza. Se evitó depender de una cuenta o de teselas públicas durante el ensayo. Queda pendiente el fondo vial conforme a la política del proveedor. [Política de teselas de OpenStreetMap](https://operations.osmfoundation.org/policies/tiles/).

Las cuentas y tokens de ciudadano viven en un modelo distinto, el servidor limita el acceso por zona y los comentarios son privados de quien los emitió. Nombre de personal, placa y trayectoria operativa ciudadana están detrás de `RSU_DEMO_DATA`. La consulta V2 conserva su forma pública mínima. La cola GPS del dispositivo está en SQLite bajo el aislamiento de Android, sin cifrado adicional de aplicación. No se certifica retención ni distribución con datos reales.
