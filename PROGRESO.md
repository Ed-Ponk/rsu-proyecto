# Progreso RSU

## V4.2

- Se conciliaron sectores, foto de zona, estado de ciudadanía y valoraciones desde `avance-07-api` en una rama aislada. Se verificaron instalación limpia y migraciones MySQL desde V4.1 y el esquema paralelo.
- Se implementó el cierre durable. El ensayo Android conservó la solicitud al terminar el proceso sin red y confirmó 12 puntos al reconectar. El segundo envío conservó la hora original.
- Se incorporó un recorte vial local de Chiclayo con atribución ODbL. Se recompilaron ambas variantes Android y se cotejaron 46 ubicaciones con el panel.
- B7 reprodujo un fallo después de abrir la app sin red. Se corrigieron la revalidación de sesión y el registro GPS obsoleto. La reprueba automática confirmó 12 coordenadas en MySQL, Ciudadano y el panel.
- Se actualizaron el [backlog](docs/aceptacion/CIERRE_V4_2.md), la [matriz acumulada](docs/aceptacion/RESULTADOS_V4_2.csv) y las [intervenciones de red pendientes](docs/despliegue/INTERVENCIONES_V4_2.md).

## V4.1

- Continuidad desde `d3fcb4f`. Se conservaron la rama, los APK, las bases y las evidencias previas.
- Salud HTTP con respuesta 503 ante MySQL inaccesible, restauración verificada en esquema aislado y 42 rutas web autenticadas que devolvieron HTML.
- B2 y B7 repetidos en Android 36 con coordenadas simuladas. La base confirmó las 12 coordenadas distintas del segmento sin red.
- Implementada caché ciudadana autenticada y versionados los APK de laboratorio. Los ensayos físicos y externos se controlan por separado.
- Ver [resultados V4.1](docs/aceptacion/RESULTADOS_V4_1.csv), [auditoría académica](docs/aceptacion/AUDITORIA_REQUISITOS_ACADEMICOS_V4.md) y [perfiles de despliegue](docs/despliegue/ARQUITECTURA_DESPLIEGUE.md).

## V3

- Revisadas 19 páginas de los avances y del plan del docente. Conservados los 105 casos, con evidencia por entorno.
- Implementados registro y valoración ciudadana, pantallas de conductor, cola GPS, indicadores y detalle operativo del panel.
- Migraciones desde cero y desde la versión previa, respaldo, restauración y concurrencia comprobados en MySQL 8.4 aislado. Permanece la regresión SQLite.
- Generados APK Android para laboratorio con origen configurable. Quedan pendientes los casos que requieren teléfono físico, conectividad externa y la cobertura indicada en el informe.
- Continuidad en [informe V3](docs/aceptacion/INFORME_V3.md) y [matriz individual](docs/aceptacion/RESULTADOS_ACEPTACION.csv).

- Clonado, estado limpio inicial en `ed1812f`, rama principal `main`, escritura en GitHub verificada.
- Línea base con SQLite y PHPUnit completada. Ver [auditoría](docs/AUDITORIA_INICIAL.md).
- Rama local `feat/mobile-platform-integration` avanzó mediante fast-forward a `842c295`. Aplicadas 16 migraciones sobre la base aislada de main.
- Aplicaciones Expo y API de consulta implementadas. Flujo ligado a recorridos existentes y token de conductor.
- Pruebas de regresión corregidas y prueba integrada incorporada. Ver [informe](INFORME_FINAL.md) para estado de entrega.
- Builds e instalación Android validados en API 36. Recorrido iniciado, recuperado, registrado con GPS ficticio, cerrado y reflejado en la consulta ciudadana.
- Rama publicada y [PR #1](https://github.com/Ed-Ponk/rsu-proyecto/pull/1) abierto como borrador.
- Próximo punto de continuidad para ampliar el piloto: teléfonos físicos, origen HTTPS, MySQL y actualización de dependencias móviles.
