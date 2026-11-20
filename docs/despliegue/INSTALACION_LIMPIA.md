# Instalación y verificación del servidor

1. Clona el repositorio y selecciona la revisión aprobada. Usa PHP con PDO MySQL, Composer, Node y MySQL. Ejecuta `composer install`, `npm ci` y `npm run build`.
2. Crea un `.env` local desde el ejemplo, define la clave de Laravel y las credenciales de una base dedicada. Crea un respaldo antes de aplicar migraciones a una copia de una versión anterior.
3. Para laboratorio Windows, exporta las variables descritas en el [manual V3](../V3_LABORATORIO.md). Ejecuta las acciones `Preparar`, `Iniciar` y `Estado` de `scripts/demo-v3.ps1`. Conserva el origen en loopback hasta que haya una red privada autorizada.
4. Para el perfil Compose, revisa `.env.docker.example`, configura claves propias, deja `WEB_BIND=127.0.0.1` y ejecuta `docker compose config --quiet` seguido de `docker compose up -d --build`. La opción `--build` permite obtener el backend del checkout seleccionado. Verifica salud, migraciones y autenticación antes de pasar el origen a un frontal externo.

Probar acceso local con `GET /api/v1/salud`. HTTP 200 identifica una instancia con el esquema accesible. HTTP 503 indica que falta acceso al esquema. Para retransmitir a un cliente, verifica además login y acceso autorizado desde ese equipo.

Se ejecutó la ruta nativa con las dependencias recuperadas de V3. Se comprobó la estructura YAML del Compose. Falta ejecutar un arranque limpio con Docker Engine.
