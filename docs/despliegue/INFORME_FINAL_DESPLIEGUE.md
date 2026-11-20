# RSU V4.1 · Estado del despliegue

| Perfil | Estado verificado |
| --- | --- |
| Laboratorio Windows | Disponible en el anfitrión con Laravel y MySQL 8.4 en loopback. Dos APK candidatos Android verificados en emuladores. |
| Exposición LAN | Bloqueada hasta habilitar red privada autorizada y probar el flujo desde un teléfono. |
| Remoto privado | Bloqueado hasta disponer de administración autorizada de la VPN y una prueba desde otra red. |
| Remoto público temporal | Sin activar. Requiere datos ficticios aislados, control de rutas y autorización del evaluador. |
| Nube independiente | Sin activar. Requiere cuenta educativa elegible, una cuota verificable o un host universitario autorizado. |
| Integración en main | PR borrador en rama existente. Faltan criterios para certificación. |

El backend `http://127.0.0.1:8104` es accesible solo desde el host. La dirección Android del laboratorio es `http://10.0.2.2:8104`. La instancia auxiliar local en 8105 respondió a la comprobación MySQL. No se obtuvo una URL física LAN ni HTTPS externo. El costo contratado adicional fue **S/ 0**.

La restauración aislada preservó 30 programaciones, 9 asistencias, 40 ubicaciones, dos ciudadanos y dos votos. Se comprobaron las filas por hash, las APIs y el historial web. Un reinicio del proceso PHP en la copia preservó el acceso con el mismo token. El backup local no cubre por sí solo archivos y secretos.

En una muestra de 30 segundos, cinco clientes enviaron 142 solicitudes de salud con MySQL local y sin errores. La mediana fue 49,97 ms y el p95 fue 135,8 ms. Para planificar carga, un conductor a intervalos nominales de cinco segundos genera 12 puntos por minuto. La consulta ciudadana de seguimiento agrega hasta 12 lecturas por minuto por cliente activo. Esta estimación y la medición de salud dejan pendiente una prueba sostenida del flujo integral.

[Resultados DEP](RESULTADOS_DEP.csv) · [Guía de emergencia](GUIA_EMERGENCIA_EXPOSICION.md) · [Comparativa de costo cero](COMPARATIVA_COSTO_CERO.md)
