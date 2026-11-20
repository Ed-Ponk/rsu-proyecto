# RSU Portable Cloud

El mismo código Laravel y la misma base MySQL alimentan el panel y las dos aplicaciones. El kit instala las dependencias de Laravel y del panel, ejecuta migraciones y puede sembrar un esquema de demostración. Se apoya en PHP con PDO MySQL, PowerShell 7.2+, Composer, Node, MySQL y Nginx instalados en el equipo. Conserva una base autorizada como origen de escritura.

## Operación del kit Windows

```powershell
./scripts/rsu-portable.ps1 -Accion Diagnosticar
./scripts/rsu-portable.ps1 -Accion Preparar -Base rsu_v3_demo_equipo
./scripts/rsu-portable.ps1 -Accion Iniciar -Nginx 'C:/RSU/nginx/nginx.exe'
./scripts/rsu-portable.ps1 -Accion Verificar
./scripts/rsu-portable.ps1 -Accion Detener -Nginx 'C:/RSU/nginx/nginx.exe'
```

Define antes las variables de entorno de base, aplicación y fixture descritas en [RC1](RC1.md). Crea el esquema vacío y un usuario limitado a ese esquema. Usa `-Php`, `-PhpCgi`, `-Mysql` o `-MysqlDump` para herramientas instaladas fuera del PATH. `Diagnosticar` identifica el perfil de red y los puertos del frontal. El kit conserva Nginx, PHP-CGI y MySQL en loopback. Mantén las contraseñas en la sesión local.

Para dos frontales locales usa directorios de Nginx distintos y puertos distintos mediante `-Puerto` y `-PuertoCgi`. La verificación utiliza el puerto que indiques. El ensayo usó un segundo frontal, ejecutó sus seis verificaciones y volvió a dejar libre el par de puertos. El servidor RC1 siguió respondiendo.

El ensayo HTTPS local usó Nginx delante de RC1 y un certificado efímero cuyo nombre alternativo correspondía a la IP de loopback. La prueba verificó el certificado y obtuvo 200 en salud y cartografía, 401 en panel privado y 404 en el archivo de entorno. Para un origen remoto utiliza una identidad TLS que el cliente pueda verificar y conserva las cookies seguras.

Para cambiar de anfitrión, suspende las escrituras, toma un respaldo con `-Accion Respaldar` y copia a través de un canal autorizado el código etiquetado, el SQL con su hash, las cargas y la clave de aplicación. Importa con `-Accion Restaurar -Base rsu_v3_demo_restore_equipo -Respaldo <archivo.sql> -Sha256 <hash>`. La restauración exige un esquema vacío. Verifica en el destino la salud, los conteos y ambos roles antes de cambiar el origen de los clientes. Mantén el anterior fuera de servicio como opción de reversión. [Procedimiento y reversión](BACKUP_RESTAURACION_ROLLBACK.md)

## Planes y alcance

| Plan | Selección y estado |
| --- | --- |
| A. Independiente | Prioriza un equipo universitario o secundario que pueda permanecer encendido. Azure for Students anuncia alta sin tarjeta, condicionada a elegibilidad y crédito disponible. GitHub Education ofrece beneficios con condiciones propias. Codespaces sirve para ensayos temporales dentro de la cuota de una cuenta personal con gasto bloqueado. Oracle requiere medio de pago para abrir una cuenta. La persistencia requiere un disco del anfitrión, no el directorio efímero del servicio. [Azure](https://azure.microsoft.com/en-us/free/students), [Education](https://education.github.com/pack), [Codespaces](https://docs.github.com/en/codespaces/troubleshooting/troubleshooting-included-usage), [Oracle](https://www.oracle.com/cloud/free/faq/) |
| B. PC remoto | Tailscale Serve puede dar HTTPS privado con cliente iOS/Android y red autorizada. Cloudflare Protected Quick Tunnel requiere PIN por correo en un navegador, sin cuenta o dominio de Cloudflare. El PIN no funciona como autenticación de los APK. Quick Tunnel público se excluye para el sistema. Zrok alojado requiere cuenta y los accesos privados necesitan un cliente compatible. [Tailscale](https://tailscale.com/docs/account/manage-plans/free-plans-discounts), [Cloudflare](https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/), [zrok](https://zrok.io/pricing/) |
| C. Servidor portátil | Windows con el lanzador anterior o un anfitrión que ejecute el Compose existente. Compose conserva MySQL y cargas en volúmenes y publica el frontal en loopback por defecto. Para operación Linux usa el Docker Engine ya autorizado en el anfitrión. El entorno actual carece de Docker operativo. |
| D. LAN sin Internet | Usa una red privada o hotspot autorizado y verifica desde un segundo cliente. El perfil de contingencia está en [la guía de emergencia](GUIA_EMERGENCIA_EXPOSICION.md). Los APK de laboratorio aceptan HTTP en IPv4 privada. Termux es experimental, requiere Android dedicado y comprobación previa de persistencia y recuperación. [Termux](https://github.com/termux/termux-app#readme) |
| E. Emuladores | scrcpy puede mostrar ambos emuladores en ventanas del anfitrión. Una sesión atendida y autenticada de escritorio permite control desde iPhone sin publicar ADB. Para habilitarla se requiere autorización para compartir la sesión de Windows y cuenta del servicio elegido. Las opciones de streaming web que exponen depuración o el escritorio sin control de acceso quedan fuera. [scrcpy](https://github.com/Genymobile/scrcpy), [Chrome Remote Desktop en iPhone](https://support.google.com/chrome/answer/1649523?co=GENIE.Platform%3DiOS&hl=en) |
| F. Evidencia | Usa los APK y el paquete de evidencias RC1, junto con capturas de laboratorio declaradas como emulación. Esta alternativa cubre una demostración reproducible. Mantén separadas las pruebas físicas de GPS y pantalla bloqueada. [RC1](https://github.com/Ed-Ponk/rsu-proyecto/releases/tag/rsu-rc1) |

El presupuesto contratado de las pruebas locales es S/ 0. La creación de recursos con crédito o cómputo facturable requiere verificar un límite efectivo y la titularidad antes de activar nada.

## Configuración móvil y Safari

Los APK publicados permiten cambiar manualmente el origen sin recompilar. Consultan salud y la versión 3 antes de guardarlo y descartan la sesión anterior al cambiarlo. Para añadir QR o vínculo, conserva la confirmación explícita del origen, el healthcheck sin credenciales y el aislamiento de tokens por servidor. La detección del equipo en LAN requiere una red autorizada y un caso de prueba de origen incorrecto antes de formar parte de la aplicación.

La pantalla Ciudadanía y su cliente HTTP pueden compartirse con Expo Web. La sesión depende de SecureStore nativo y la caché de Expo SQLite. La versión web debe usar almacenamiento y autenticación compatibles con Safari, preferir el mismo origen que la API y evitar persistir credenciales reutilizables en almacenamiento accesible a JavaScript. Una PWA debe comprobar instalación desde la pantalla de inicio y funcionamiento con sesión y mala red. [Expo Web](https://docs.expo.dev/workflow/web/), [PWA](https://docs.expo.dev/guides/progressive-web-apps/)

La publicación externa de RSU queda condicionada a que el titular autorice la prueba y el destinatario. Emplea una copia aislada con datos ficticios, protege tanto el panel como la API, valida cookies seguras y un acceso denegado, y detén el túnel al finalizar.

Se registró una conexión HTTPS con cloudflared 2026.11.1 hacia un servidor separado que solo devolvía texto sintético. Una solicitud anónima recibió 302 y el contenido quedó protegido. El proceso se cerró tras la prueba. La comprobación del PIN y del flujo RSU queda pendiente del destinatario autorizado.

