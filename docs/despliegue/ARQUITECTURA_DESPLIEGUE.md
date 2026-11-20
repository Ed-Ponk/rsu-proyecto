# Decisión de arquitectura para el despliegue

El panel y las dos aplicaciones usan el mismo Laravel. Todas las operaciones confirmadas y las sesiones web residen en MySQL. La cola nativa del conductor conserva capturas aún no confirmadas. Los dos APK Android conservan sus identificadores independientes.

| Perfil | Frontal y base | Evidencia y condición pendiente |
| --- | --- | --- |
| Laboratorio | PHP en loopback. MySQL 8.4 en loopback. Emuladores con el alias Android `10.0.2.2`. | Arranque local y salud verificados. Sirve para ensayos con datos ficticios. |
| LAN | Interfaz privada autorizada y MySQL local. El archivo Compose publica solo el frontal. | La red activa se clasificó como pública. Hace falta conectar una red privada y comprobar desde un teléfono físico. |
| Remoto privado | VPN autorizada y frontal HTTPS sobre el origen privado. | Hace falta confirmar la cuenta administradora, aplicar reglas de acceso y probar desde otra red. |
| Remoto temporal | Proxy HTTPS con autenticación, rutas acotadas y origen ficticio aislado. | Pendientes política de acceso para los APK y revisión del titular. |
| Nube independiente | PHP y MySQL persistente en el mismo host o red privada dedicada. | Requiere un proyecto con cuota, retención y costo cero comprobables. |

El Compose existente conserva volúmenes para MySQL y archivos. El frontal escucha por defecto en loopback y phpMyAdmin requiere activar el perfil `administracion`. Su puerto sigue en loopback. MySQL se queda en la red interna del Compose. El endpoint de salud devuelve 503 sin diagnóstico interno si no puede leer el esquema. Nginx limita ese endpoint antes de llegar a PHP para que un fallo de MySQL no invalide el control.

Las plantillas de acceso y panel usan las fuentes locales del sistema y los assets compilados. El perfil de laboratorio HTTP se limita a equipos y redes de demostración. Para un perfil remoto se exige TLS confiable, autorización separada por rol y cuentas ficticias propias. El servidor PHP integrado queda limitado al laboratorio. En exposición externa usa el frontal de despliegue y verifica autenticación, cookies seguras y restricciones de rutas desde una red diferente.
