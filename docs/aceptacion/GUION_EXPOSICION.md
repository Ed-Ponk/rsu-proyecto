# Guion de exposición RSU V3

Prueba primero en una base MySQL aislada. A opera el panel y el inicio del conductor. B demuestra el recorrido, la desconexión y el cierre. C crea la cuenta ciudadana y confirma la zona. D sigue el recorrido y comprueba la valoración. Con dos personas, A toma C y B toma D. Usa identidades ficticias y protege las claves del ensayo.

1. A abre Programaciones, muestra el camión y los dos ayudantes, y deja una asistencia pendiente.
2. C registra la cuenta, confirma la zona y abre el detalle de la programación.
3. A entra en el APK de conductor, comprueba el bloqueo y marca la asistencia que falta desde el panel.
4. B recarga, acepta el inicio y muestra el mapa. D espera el cambio de estado en el ciudadano.
5. B reproduce una ruta de prueba, interrumpe la red y comprueba el contador de pendientes. D muestra la antigüedad de la última lectura.
6. B restaura red, comprueba el reenvío y finaliza con observación. D registra una valoración y abre el historial.
7. A coteja el historial del panel. Guarda capturas con hora y anota duración, dispositivo, red y motor en el CSV.

Repite el ensayo sobre otra base de demostración. Si el GPS falla, verifica permiso y señal en el dispositivo antes de iniciar. Una traza ficticia del emulador permite ensayar la exposición y se presenta como simulada. Consulta el [resultado individual](RESULTADOS_ACEPTACION.csv) antes de declarar cobertura.
