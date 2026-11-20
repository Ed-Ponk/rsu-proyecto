# Contingencia de exposición

## Plan A · Red privada probada

1. En el equipo servidor, comprueba MySQL y ejecuta `scripts/demo-v3.ps1 -Accion Estado -Escucha <IP-privada> -Puerto 8104`. La salud debe responder HTTP 200 y versión 3.
2. Usa la interfaz de una red privada bajo control del equipo. Si el servidor requiere cambiar la escucha, detenlo con los parámetros de su arranque y vuélvelo a iniciar con la IP privada. Mantén MySQL en loopback.
3. En ambos teléfonos, abre primero el endpoint de salud y luego configura el mismo origen en los APK de laboratorio. Accede al panel y confirma que el servicio creado se refleja en ambas aplicaciones. El cliente tiene que estar en la red probada.

## Plan B · Wi-Fi privado o hotspot sin Internet

1. Conecta el servidor y ambos teléfonos al hotspot autorizado. Si dos clientes no pueden comunicarse, utiliza un punto de acceso propio que permita ese tráfico o conecta los teléfonos al hotspot del servidor.
2. Conserva el mismo MySQL y el mismo almacenamiento. Tras el cambio de IP, resuelve primero los recorridos y lotes pendientes del origen anterior. Cambia la URL, vuelve a iniciar sesión y verifica una nueva programación en ambos teléfonos.
3. Retira Internet externo dejando activa la LAN y comprueba asistencia, inicio, seguimiento, cierre y valoración. El fondo vial queda pendiente. El mapa vectorial usa datos del servidor.

## Plan C · Emuladores y evidencia

Usa el origen `http://10.0.2.2:8104` para emuladores sobre el host de laboratorio. Identifica los datos y el GPS como simulados. Si restauras en otro esquema, informa qué copia y qué fecha estás usando.

La LAN física, el hotspot y el flujo sin Internet siguen pendientes de ensayo. Una respuesta HTTP de salud desde el servidor solo acredita la conectividad local de ese equipo.
