# Escenarios para evaluación libre

- **¿Qué sucede con una asistencia incompleta?** La regla del servidor restringe el inicio y el APK muestra la asistencia por integrante. Prueba con distintos motivos y turnos.
- **¿Qué ocurre si la red cae durante el recorrido?** El APK encola puntos con UUID. B7 comprobó la recuperación de un segmento y el rechazo del duplicado lógico.
- **¿Puede otro ciudadano leer un comentario?** El servidor calcula el agregado y devuelve solo el comentario propio. La copia móvil guarda las estrellas propias sin guardar el comentario.
- **¿Cómo se verifica que la copia es recuperable?** El respaldo se restaura en un esquema vacío y se comparan filas, conteos y consultas. El protocolo exacto está en [respaldo y recuperación](../despliegue/BACKUP_RESTAURACION_ROLLBACK.md).
- **¿Funciona desde otra red?** El perfil comprobado usa loopback y emuladores. Los perfiles LAN y remoto dependen de las pruebas físicas descritas en el [estado del despliegue](../despliegue/INFORME_FINAL_DESPLIEGUE.md).
