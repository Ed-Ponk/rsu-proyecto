# Informe de entrega RSU

**Estado general · APTO CON LIMITACIONES para laboratorio Android.** El panel y las dos aplicaciones completaron el flujo en el servidor local y un emulador Android 36. Quedan pendientes las pruebas con teléfonos físicos, iOS nativo, MySQL y un despliegue con HTTPS para acceso desde otras redes.

## Evidencias

- `main` partía de `ed1812f`. La suite Feature original pasó 36 pruebas con 98 aserciones y 6 omisiones. Se integraron los avances existentes hasta `842c295` mediante fast-forward y se aplicaron 16 migraciones a la base SQLite creada desde main.
- La suite de la integración registró 183 casos, 176 aprobados, 6 omitidos por configuración y 1 incompleto por KPI del panel. Total 1 757 aserciones, sin errores ni fallos. Pint y Vite pasaron. `composer audit --locked` quedó sin avisos después de actualizar 7 dependencias.
- La prueba de integración certifica el flujo programado → en ruta → completado con persistencia en SQLite y sin datos de personal o GPS en la respuesta pública.
- Laravel en `http://127.0.0.1:8102` respondió a salud y consulta con 200, a panel sin sesión con 302 y a API de conductor sin token con 401. En una base separada con cinco zonas de ejemplo se verificó el ciclo real por HTTP de la programación 29, recorrido 1 y un punto GPS.
- El acceso web con sesión llegó al listado de programaciones y mostró el recorrido Completado en su historial. Una segunda instancia PHP recuperó el mismo estado desde SQLite. En 20 consultas locales por zona, la mediana fue 24,6 ms y el percentil 95 fue 27,5 ms. La prueba fue secuencial y con datos de ejemplo.
- Expo validó dependencias y compiló bundles Android e iOS. Gradle generó ambos APK para `arm64-v8a` y `x86_64`, de aproximadamente 34,86 MB cada uno. Ambas firmas se verificaron con `apksigner`. Android identificó `pe.rsu.cliente` y `pe.rsu.conductor`; la variante ciudadana carece de permiso de ubicación. El arranque frío inicial de la actividad ciudadana en el emulador fue de 433 ms en una muestra.
- Por ADB se instalaron ambas apps. El conductor inició el recorrido, la variante ciudadana mostró **En ruta**, y después de forzar el cierre y reabrir el conductor recuperó el recorrido. Registró una ubicación ficticia emitida por el emulador y finalizó con observación. SQLite conservó la programación 30, el recorrido 2 en estado completado y un punto GPS. La consulta ciudadana presentó **Recorrido completado**.
- Al retirar el reenvío ADB, la aplicación ciudadana mostró un error de conexión y conservó el último resultado. Al restaurarlo, volvió a consultar el servidor y retiró el aviso.
- La aplicación de cliente se interpreta como consulta vecinal de la recolección municipal. Se añadieron horarios por zona y estado operativo sin registro ciudadano. La app del conductor reutiliza cuenta, propiedad, asignación y asistencia existentes. El GPS se registra manualmente en primer plano. El cambio de estado se consulta en REST cada 30 segundos en la app vecinal.

## Criterios

| Criterio | Estado | Evidencia o dependencia |
| --- | --- | --- |
| Repositorio original clonado y auditado | APROBADO | Referencias, arquitectura y [auditoría](docs/AUDITORIA_INICIAL.md) |
| Sistema original evaluado antes del cambio | APROBADO | Migraciones, seed y 36 pruebas Feature |
| Reglas de negocio identificadas y documentadas | PARCIAL | Catálogo por flujo. Falta inventario campo por campo de todos los módulos |
| Validaciones existentes y faltantes analizadas | PARCIAL | [Matriz](docs/MATRIZ_DE_VALIDACIONES.md), queda concurrencia y validación exhaustiva por módulo |
| Matriz de trazabilidad | APROBADO | [Regla, ruta y prueba](docs/MATRIZ_DE_TRAZABILIDAD.md) |
| Arquitectura móvil seleccionada y justificada | APROBADO | [ADR](docs/DECISIONES_TECNICAS.md) |
| Servidor principal funcional | APROBADO | HTTP y PHPUnit en SQLite |
| API documentada y probada | APROBADO | [Contrato](docs/API.md) y pruebas de API |
| App cliente implementada | APROBADO | APK conectado y validado en emulador Android 36 |
| App conductor implementada | APROBADO | APK conectado, recuperación de sesión y GPS manual validados con ubicación de prueba |
| Apps conectadas al servidor real | APROBADO | Ambas instalaciones accedieron a Laravel por ADB reverse |
| Roles y permisos diferenciados | APROBADO | Pruebas de acceso y propiedad |
| Base de datos integrada | APROBADO | SQLite desde cero y actualización desde main. MySQL pendiente de validación |
| Comunicación y sincronización | APROBADO | Transiciones entre ambas apps y recuperación del recorrido activo |
| Flujo crítico extremo a extremo | APROBADO | Ambas apps Android, Laravel, SQLite e historial administrativo |
| Funcionamiento web preservado | APROBADO | Pruebas de regresión, build y panel HTTP. MySQL pendiente de validación |
| Entorno gratuito operativo | APROBADO | Loopback y ADB reverse. HTTPS y otras redes pendientes |
| APK | APROBADO | Dos instaladores firmados para laboratorio Android |
| Pruebas en dispositivos o limitación documentada | APROBADO | Emulador Android 36. ADB no detectó teléfonos físicos. Sin compilación nativa iOS |
| Seguridad revisada | PARCIAL | Expiración, alcance y dependencias PHP. Auditoría npm móvil y pruebas de despliegue pendientes |
| Instalación reproducible | APROBADO | PHP/SQLite, Expo y Gradle verificados en Windows con rutas cortas |
| Cambios registrados mediante Git | APROBADO | Rama `feat/mobile-platform-integration` |
| Rama publicada con permisos | APROBADO | `origin/feat/mobile-platform-integration` |
| Pull Request preparado o creado | APROBADO | [PR #1 en borrador](https://github.com/Ed-Ponk/rsu-proyecto/pull/1) |
| Informe con evidencias | APROBADO | Este documento y [pruebas](docs/PRUEBAS.md) |

Consulta la [instalación](docs/DESPLIEGUE_GRATUITO.md) y el [plan manual](docs/PRUEBAS.md). Coste contratado S/ 0.

## Artefactos Android de laboratorio

| APK | SHA-256 |
| --- | --- |
| `RSU-cliente-laboratorio.apk` | `6b9cc9b991ea26f04de8cd651888b03574f4d3062249035ae90a451cd8d96dc2` |
| `RSU-conductor-laboratorio.apk` | `077d7e2cc3d5c02cc5bebabf64bcc97646452e8305936f3d277e2e7f86444ee4` |

Instalar ambos con ADB y ejecutar `adb reverse tcp:8102 tcp:8102` en el dispositivo de prueba. Estos APK apuntan al servidor del PC por el puerto 8102. Evidencias en `RSU-conductor-en-ruta.png`, `RSU-cliente-en-ruta.png` y `RSU-cliente-completado.png`.
