#!/bin/sh
# Arranque del contenedor RSU: prepara la aplicación y luego lanza Apache.
# Todo se configura con variables de entorno (ver .env.docker.example); la imagen no trae claves.
set -e
cd /var/www/html

echo "[rsu] Iniciando..."

# 1) Rechazar los valores de ejemplo: obliga a poner claves propias.
for variable in DB_PASSWORD ADMIN_PASSWORD MYSQL_ROOT_PASSWORD; do
    valor=$(printenv "$variable" || true)
    case "$valor" in
        CAMBIAR_*) echo "[rsu] ERROR: la variable $variable todavía tiene el valor de ejemplo. Edite su archivo .env y ponga una clave propia." >&2; exit 1 ;;
    esac
done

# 2) APP_KEY: si no se indicó, se genera una vez y se guarda en el volumen de storage (así no cambia al reiniciar).
if [ -z "$APP_KEY" ]; then
    archivo=storage/app/.app_key
    if [ ! -s "$archivo" ]; then
        echo "[rsu] Generando APP_KEY..."
        php artisan key:generate --show > "$archivo"
        chmod 600 "$archivo"
    fi
    APP_KEY=$(cat "$archivo")
    export APP_KEY
fi

# 3) Esperar a que MySQL acepte conexiones.
echo "[rsu] Esperando a la base de datos ${DB_HOST:-db}:${DB_PORT:-3306}..."
intentos=0
until php -r '
    try {
        new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: "3306").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
    } catch (Throwable $e) { fwrite(STDERR, "   ".$e->getMessage()."\n"); exit(1); }
' 2>/dev/null; do
    intentos=$((intentos + 1))
    if [ "$intentos" -ge 60 ]; then
        echo "[rsu] ERROR: la base de datos no respondió a tiempo." >&2
        exit 1
    fi
    sleep 2
done
echo "[rsu] Base de datos lista."

# 4) Permisos de las carpetas que se montan como volúmenes.
mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs public/uploads
chown -R www-data:www-data storage bootstrap/cache public/uploads 2>/dev/null || true

# 5) Tablas y datos iniciales (catálogos + administrador; datos de ejemplo si SEED_DEMO=true). Solo la primera vez.
php artisan migrate --force --no-interaction
marca=storage/app/.sembrado
if [ ! -f "$marca" ]; then
    echo "[rsu] Cargando datos iniciales..."
    php artisan db:seed --force --no-interaction
    date > "$marca"
fi

# 6) Cachés de producción (ya con la configuración real del contenedor).
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[rsu] Listo. Lanzando el servidor web."
exec docker-php-entrypoint "$@"
