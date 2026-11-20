#!/bin/sh
# Crea el archivo de variables para Docker con claves aleatorias (Linux / macOS / Git Bash).
#   Computadora nueva (sin .env):       sh docker/crear-env.sh
#   Si ya tiene un .env de desarrollo:  sh docker/crear-env.sh .env.docker
#                                       y luego:  docker compose --env-file .env.docker up -d
set -e
cd "$(dirname "$0")/.."
destino="${1:-.env}"
if [ -f "$destino" ]; then echo "Ya existe $destino: no se modifica. Borrelo si quiere generarlo de nuevo."; exit 0; fi

clave() { LC_ALL=C tr -dc 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789' < /dev/urandom | head -c "${1:-24}"; }
admin="Rsu$(clave 12)7"

sed -e "s/CAMBIAR_CLAVE_ADMIN/$admin/" \
    -e "s/CAMBIAR_CLAVE_BD/$(clave 24)/" \
    -e "s/CAMBIAR_CLAVE_ROOT/$(clave 24)/" \
    .env.docker.example > "$destino"

echo "Archivo $destino creado."
echo "Administrador:  admin@rsu.local"
echo "Contrasena:     $admin"
echo "Guardela: no se vuelve a mostrar (esta en el archivo $destino)."
