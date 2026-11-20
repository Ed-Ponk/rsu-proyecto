# Seguridad y privacidad del despliegue

Las cuentas y rutas de conductor, ciudadano y panel aplican autorización en Laravel. Antes de ofrecer acceso a evaluadores reemplaza credenciales conocidas de las semillas, separa el fixture de cualquier dato real y verifica permisos de cada rol. Mantén `APP_DEBUG=false`, guarda las credenciales fuera del repositorio y usa un usuario MySQL limitado a su esquema.

El Compose deja MySQL sin puerto publicado y phpMyAdmin en loopback bajo perfil opcional. El frontal limita la ruta de salud y la respuesta omite detalles internos. Para acceso remoto faltan TLS confiable, revisión del proxy y pruebas de autorización desde fuera. Las transmisiones GPS y datos de personal del perfil de demostración corresponden a datos ficticios. Ver [seguridad del proyecto](../SEGURIDAD.md).

El APK ciudadano guarda consultas autenticadas en SQLite bajo aislamiento de la app. Las separa por una huella del token, origen y zona, limita la lectura a seis horas y guarda hasta 40 consultas por sesión. La copia omite DNI, correo, nombres del equipo, placa y comentario de valoración. Las copias de seguridad del sistema operativo están desactivadas en Android. La sesión local borra las filas cuando cierra o cambia de zona. Una desconexión impide conocer revocaciones externas hasta recuperar el contacto con el servidor.
