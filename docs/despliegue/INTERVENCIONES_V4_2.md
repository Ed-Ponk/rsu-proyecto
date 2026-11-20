# Intervenciones para completar la certificación

| Tarea | Motivo y recurso | Preparado | Intervención y comprobación posterior |
| --- | --- | --- | --- |
| LAN y hotspot | Ethernet figura como red pública. Falta un cliente en una red privada autorizada. | Servidor, salud, MySQL en loopback y [contingencia](GUIA_EMERGENCIA_EXPOSICION.md). | Indicar o habilitar la red privada de ensayo. Comprobar desde un segundo equipo, sin publicar MySQL. |
| GPS físico | ADB detectó únicamente dos emuladores. | APK Android, casos de GPS simulado y cierre offline verificado. | Conectar un Android autorizado y aceptar la depuración para las pruebas. En Wi-Fi y sin USB, repetir GPS con pantalla bloqueada y los recorridos de exposición. |
| iPhone | Falta acceso físico a Safari y una URL HTTPS autorizada. | El panel puede abrirse en Safari. Expo documenta Expo Go para SDK 54 en App Store. | Conectar el iPhone a la red de ensayo y comprobar Safari. Una prueba ciudadana con Expo Go requiere SDK 54 y acceso a Metro y a la API. [Compatibilidad de Expo Go](https://docs.expo.dev/troubleshooting/expo-go-version-mismatch/) |
| HTTPS externo | Falta un titular autorizado y un cliente en otra red. | [Plan HTTPS](ACCESO_REMOTO_HTTPS.md) y [comparativa](COMPARATIVA_COSTO_CERO.md). | El titular debe habilitar la VPN y asignar acceso solo al frontal. Repetir el ciclo desde una conexión ajena a la LAN. |
| Nube independiente | Falta un host con cuota y almacenamiento MySQL garantizados a S/ 0. | Respaldo y restauración en MySQL comprobados. | Confirmar acceso al host universitario o cuenta académica elegible. Probar el sistema con el PC original apagado. |
| Workflow adicional de MySQL y Expo | La credencial del PR carece del alcance de workflow y la integración devolvió 403. | Suite MySQL y builds ejecutados localmente. | El propietario puede conceder el permiso específico o incorporar el workflow desde su cuenta autorizada. |
