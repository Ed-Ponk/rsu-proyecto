# Acceso remoto HTTPS

El laboratorio actual está disponible únicamente desde el host y sus emuladores. Para habilitar acceso externo, usa una copia ficticia con credenciales nuevas y aclara quién puede administrar, invitar y revocar integrantes.

El candidato preferido es Serve dentro de una VPN privada. Una vez que el titular confirme elegibilidad, instala el cliente en el anfitrión y en cada equipo autorizado. Aplica una política que permita al grupo evaluador acceder solo al frontal HTTPS, y mantén PHP, MySQL y herramientas de administración fuera del alcance remoto. Usa el certificado emitido para el nombre del anfitrión asignado por la VPN. Comprueba el panel, ambos APK y la revocación desde un teléfono conectado a otra red. [Tailscale Serve](https://tailscale.com/kb/1312/serve)

Un túnel público temporal exige una revisión independiente. Configura las rutas del panel y la API que requiere el ensayo, autenticación por canal, TLS y límites de uso. Comprueba que el acceso ajeno se rechaza y retira el túnel después del ensayo. El laboratorio no tiene URL remota probada.
