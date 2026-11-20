# Inventario y estado de infraestructura

- Equipo Windows con PHP 8.4.22, Composer, Node, MySQL 8.4, Android SDK y dos emuladores Android 36. Las copias de V3 permanecen disponibles. MySQL escucha en loopback en el puerto 3317.
- Instancias locales recuperadas en 8104 y 8105. El origen 8105 respondió desde el equipo anfitrión y la prueba controlada del puerto 8106 devolvió 503 cuando el esquema fue inaccesible. La instancia temporal se detuvo.
- Solo se detectaron emuladores en ADB. La interfaz Ethernet activa tiene perfil público. El equipo de trabajo debe habilitar una red propia autorizada y aportar un Android físico para verificar el acceso desde otro dispositivo.
- Se detectó un cliente ngrok instalado. Quedan por verificar su plan, su titular y el control de acceso. El conector Railway mostró un proyecto existente con un servicio MySQL. La sesión mantuvo el proyecto sin cambios.
- Docker Engine no está disponible mediante el ejecutable esperado ni en el PATH comprobado. Se inspeccionó la configuración Compose sin iniciar contenedores.
