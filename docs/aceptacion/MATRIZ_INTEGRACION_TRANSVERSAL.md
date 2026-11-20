# Comprobaciones de propagación

El panel administrado controla asignaciones, asistencia y catálogos. El conductor autorizado controla el recorrido y la sincronización GPS. El ciudadano puede consultar su zona y valorar servicios finalizados. La trayectoria compartida se limita al perfil ficticio de demostración, y el comentario de una valoración permanece privado.

| Operación | Observación V4.1 | Pendiente |
| --- | --- | --- |
| Crear programación | Fixture visible mediante las dos API y panel | Crear por el formulario y verificar ambas apps abiertas |
| Modificar programación | Regresión PHP y formulario accesible | Operación de panel y refresco en ambos APK |
| Reprogramar servicio | Vista de cambios accesible | Cambio con motivo y cotejo integral |
| Cambiar vehículo | Catálogo y formulario disponibles | Propagación de la nueva asignación |
| Reemplazar conductor | Pruebas de reglas y restricciones existentes | Reemplazo en panel y pérdida de acceso del conductor anterior |
| Cambiar ayudantes | Detalle del recorrido muestra equipo asignado | Cambio y cotejo visible de asistencia |
| Registrar asistencia | Regresión de marcación y asistencia aprobada | Propagación de una marcación nueva |
| Completar asistencias | El conductor inició contra un fixture de asistencia completa | Última asistencia registrada en panel durante la prueba |
| Modificar turno | Listado y formulario cargaron | Horario actualizado en ambos APK |
| Cambiar zona | API y autorización cubiertas por la regresión | Programación reasignada durante una sesión activa |
| Iniciar recorrido | B2 y B7 confirmaron el recorrido del conductor y seguimiento por API | Segundo APK abierto durante la transición y cotejo visual |
| Registrar GPS | B7 confirmó 26 puntos al reconectar y 41 al cerrar. Coincidieron API y panel | Validación en teléfonos físicos |
| Perder conexión | B7 confirmó cola local en Android. D6 mostró edad de la información ciudadana | Probar ambos cortes en la misma ejecución |
| Recuperar conexión | B7 confirmó el tramo offline. D6 se actualizó sin reiniciar | Validación simultánea en ambos APK |
| Finalizar recorrido | Cierre mediante API del conductor y detalle visible desde el panel | Cierre offline durable y verificación en APK ciudadano durante el cambio |
| Registrar calificación | API ciudadana persistió y devolvió un voto. Restauración reprodujo un promedio de 4,5 con dos votos | Confirmar indicador administrativo de una nueva valoración enviada desde el APK |
| Revocar acceso | Regresión con roles y tokens | Revocación administrativa mientras ambos APK están activos |
