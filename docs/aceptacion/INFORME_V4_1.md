# RSU V4.1 · Informe de aceptación

**Apto para continuar ensayos en laboratorio. Exposición física y certificación completa pendientes.** La [matriz de 129 casos](RESULTADOS_V4_1.csv) registra un estado V4.1 por identificador y conserva los 105 resultados V3 como antecedentes. Una prueba previa o una ejecución de API parcial no sustituye los criterios de la interfaz.

Esta ronda verifica **11 casos base aprobados, 15 bloqueados y 103 pendientes de una ejecución completa**. La matriz conserva las comprobaciones parciales y sus evidencias.

## Resultados observados

- Regresión SQLite de 187 casos registrados. Pasaron 181 y se omitieron seis de funcionalidades de Jetstream desactivadas. La suite de API en MySQL 8.4 aprobó 19 casos. Pasaron Pint y Vite.
- El panel devolvió 42 vistas HTML autenticadas, con listados, formularios y vistas operativas. La prueba fue de navegación y renderizado. Aún falta ejecutar y documentar todas las mutaciones y restricciones desde las interfaces.
- B2 mostró la transición de búsqueda con cero puntos a primera ubicación confirmada en MySQL. El emulador entregó inicialmente una coordenada simulada distinta de la inyectada. Las capturas y el identificador MySQL registran lo que recibió realmente la app.
- B7 confirmó los 12 puntos simulados del segmento offline y la eliminación del estado pendiente. El servidor mantuvo 10 ubicaciones durante el corte. El reenvío de un UUID respondió como repetido sin insertar otra fila. La API ciudadana y el panel recuperaron el recorrido y el voto. Quedan pendientes la reproducción con Android físico, durante un reinicio y la inspección simultánea mediante el APK ciudadano.
- Los dos APK candidatos de laboratorio llevan versión 1.2.0, firma de laboratorio y arquitectura arm64-v8a/x86_64. Los ensayos B2/B7 se hicieron con el APK V3 y servidor actualizado. La versión 1.2.0 se verificó por separado.
- El APK ciudadano recuperó historial y detalle tras terminar el proceso sin red. Mostró la fecha de la última consulta y omitió datos privados. Actualizó automáticamente después de reconectar.
- D6 se reprodujo durante un minuto en el seguimiento de un recorrido finalizado. C13 eliminó un token en MySQL y pidió iniciar sesión al volver a abrir. El APK Ciudadano entregado excluye los permisos de ubicación en segundo plano. Se comprobó el manifiesto binario.

## Riesgos abiertos

- Faltan el fondo vial con controles, el cierre offline durable, las pruebas de propagación restantes, los documentos fuente de avances anteriores que no se han identificado con certeza y dos ensayos completos.
- Faltan teléfonos autorizados para LAN, hotspot y GPS con pantalla bloqueada. La prueba de red externa exige un titular de VPN o un frontal HTTPS aprobado y otro acceso a Internet.
- Los APK son candidatos de laboratorio. El host remoto y los APK de distribución con origen HTTPS fijo siguen pendientes.
- El workflow ampliado de MySQL y Expo sigue fuera de GitHub por el 403 de V3. Las verificaciones equivalentes disponibles se ejecutaron localmente.

[Informe de despliegue](../despliegue/INFORME_FINAL_DESPLIEGUE.md) · [Auditoría de requisitos](AUDITORIA_REQUISITOS_ACADEMICOS_V4.md)
