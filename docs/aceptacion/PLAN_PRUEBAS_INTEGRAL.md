# Plan integral V3

Los pasos y resultados esperados se conservan de las referencias. Marca cada caso al ejecutarlo y registra la evidencia por entorno.

| Caso | Pasos | Resultado esperado | Registro |
| --- | --- | --- | --- |
| I1 | Crear programación en panel con vehículo, conductor, 2 ayudantes y zona; consultar en ambas apps. | Una sola programación persistida, datos coherentes y ciudadano solo ve campos autorizados. | ☐ |
| I2 | Dejar ayudante ausente, probar inicio desde app y API, marcar asistencia y recargar. | Inicio rechazado antes, permitido después dentro de ventana; servidor es autoridad. | ☐ |
| I3 | Enviar inicio simultáneo desde dos sesiones del mismo conductor y/o de dos conductores no autorizados. | Solo recorrido válido único; rechazos coherentes; transacciones y restricciones MySQL verificadas. | ☐ |
| I4 | Iniciar y recorrer una ruta con varias lecturas; observar ciudadano y admin simultáneamente. | Estado y puntos coinciden con BD; medir latencia real, no basarse solo en capturas. | ☐ |
| I5 | Bloquear pantalla 1–2 min y caminar con teléfono autorizado. | Notificación visible y puntos del tramo; registrar modelo, versión y restricciones del SO. | ☐ |
| I6 | Interrumpir la red del conductor durante 1 min y restaurarla. | Cola durable, reenvío completo, conteo cero, sin duplicados; ambos observadores se actualizan. | ☐ |
| I7 | Cerrar/reabrir app o reiniciar servidor durante recorrido con operaciones pendientes. | Recupera estado persistente; no crea otro recorrido, puntos confirmados no se pierden. | ☐ |
| I8 | Solicitar finalización simultánea con envío de puntos o cola pendiente. | Transición consistente y auditada; detener GPS según protocolo, resolver pendientes sin pérdida/registros indebidos tras cierre. | ☐ |
| I9 | Finalizar, valorar desde 2 ciudadanos elegibles, repetir voto y consultar historial. | Estado terminal coherente; 2 valoraciones únicas, promedio/conteo correctos, historial con datos autorizados. | ☐ |
| I10 | Probar tokens cruzados, IDs ajenos, revocación y datos privados en las 3 interfaces. | Acceso no autorizado rechazado; no fuga de información ni escritura indebida. | ☐ |
| I11 | Ejecutar ciclo completo con MySQL en Wi-Fi sin ADB reverse y repetir con HTTPS seguro desde otra red autorizada. | Conexión real y persistencia tras reinicio; reportar por separado Wi-Fi y acceso externo, sin aprobar un entorno por el otro. | ☐ |
| I12 | Restaurar copia aislada, ejecutar ensayo completo dos veces y cotejar artefactos. | Fixtures repetibles, documentación ejecutable, APK correspondientes al commit y evidencia verificable. | ☐ |
