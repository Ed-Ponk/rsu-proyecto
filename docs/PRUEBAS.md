# Pruebas

Ejecutar con una copia aislada, `APP_KEY` generada y assets compilados.

```text
php vendor/bin/phpunit
php vendor/bin/pint --test
composer audit --locked
npm ci
npm run build
cd mobile
npm ci
npm run check
```

`ConductorApiTest` prueba credenciales, rol, propiedad, vencimiento, logout, GPS y estados. Su flujo integrado usa SQLite efímera y datos ficticios para confirmar programado, en ruta y completado. Verifica persistencia y ausencia de datos personales y GPS en la respuesta ciudadana.

Se validaron por HTTP local `/up` y consulta con 200, panel invitado con 302 y conductor invitado con 401. La consulta fría dio 79 ms en la muestra con SQLite y sin zonas cargadas. Es una observación de arranque, con carga y percentiles pendientes.

Una segunda prueba, sobre la copia persistente y con datos ficticios, usó el login HTTP, consultó la programación 29 de la zona 5, inició el recorrido 1, registró un punto GPS, cerró con una observación y revocó el token. Las lecturas ciudadanas sucesivas devolvieron `programado`, `en_ruta` y `completado`.

El login web con CSRF y sesión de demostración devolvió 200 en el panel y en el listado de programaciones. El historial de la programación 29 devolvió 200 y el estado Completado. Un proceso PHP nuevo recuperó el mismo estado desde SQLite. Veinte lecturas secuenciales de una zona con datos, en el mismo PC, dieron una mediana de 24,6 ms y un percentil 95 de 27,5 ms. La muestra mide el cliente HTTP y el servidor en conjunto, con un solo usuario.

## Prueba en dos dispositivos

1. Arranca el servidor aislado, genera ambas variantes con la URL de prueba e instala cada app.
2. En el panel, programa el turno actual con zona, vehículo y personal. Registra las asistencias del turno.
3. Abre la zona en RSU Recolección. Inicia sesión en RSU Conductor y comprueba horario y asistencia.
4. Inicia recorrido y verifica **En ruta** desde la otra app. Registra un punto con permiso concedido.
5. Deniega ubicación y confirma que puedes seguir consultando el turno. Reactiva el permiso y registra un punto.
6. Simula una interrupción con modo avión, reactiva la red y actualiza. Cierra la app y confirma que recupera el recorrido activo.
7. Finaliza con una observación y confirma **Recorrido completado** en la otra app. Comprueba el historial.
8. Reinicia el servidor y verifica que los datos y estados existentes se conservan. Prueba en un segundo teléfono con otra cuenta y comprueba que no ve asignaciones ajenas.

En el emulador Android 36 se ejecutó el flujo de inicio desde el APK, consulta ciudadana de En ruta, cierre forzado del proceso, recuperación del recorrido desde SecureStore y Laravel, GPS ficticio, cierre con observación y relectura ciudadana. SQLite confirmó la programación 30 completada y un punto asociado al recorrido 2. En el proceso de prueba se reprodujo una lectura GPS sin respuesta, que ahora tiene un límite de 15 segundos.

Se quitó y restauró el reenvío ADB con el APK ciudadano final. La aplicación conservó el último resultado al fallar la consulta y se recuperó después de reconectar.

Quedan pendientes teléfonos físicos, la ejecución nativa de iOS, lector de pantalla, carga representativa, concurrencia en MySQL y redes degradadas con latencia. La prueba operativa del panel conserva un caso incompleto para el conjunto de KPI pendiente.
