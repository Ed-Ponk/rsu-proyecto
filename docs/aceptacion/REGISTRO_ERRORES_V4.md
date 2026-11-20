# Incidencias V4.1

| Hallazgo | Cambio o verificación |
| --- | --- |
| Salud devolvía HTTP 200 con el servidor PHP arriba y MySQL inaccesible | Se añadió lectura mínima del esquema. Nginx limita la ruta fuera de Laravel para que el control no dependa de la base consultada. La reprueba HTTP obtuvo 503 limpio. |
| El primer método de probar el esquema ausente retiraba la tabla de migraciones del esquema desechable | Se aisló la condición en una conexión temporal. Se repitió la suite MySQL en un mismo esquema de prueba en ejecuciones consecutivas. |
| Autolinking Android reutilizó el identificador de otra variante | Se regeneraron los archivos de autolinking y se verificaron los identificadores de ambos APK. |
| Ciudadano heredó permisos de ubicación en segundo plano de una generación previa | Se bloquearon explícitamente los permisos de segundo plano de Ciudadano. Se inspeccionó la lista de permisos de los APK instalados. |
| La lectura inicial de B2 venía de una vista incompatible con el inspector de interfaz | Se usaron capturas directas del emulador y se cotejaron contra el servidor. |
| V3 recuperaba la cola, pero no distinguía un tramo B7 | Se diseñaron tres segmentos con 19 coordenadas distintas y se confirmó cada punto B en MySQL. |
