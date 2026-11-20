# RSU RC1

## Instalación

Instala PHP con PDO MySQL, Composer, Node y MySQL. Ejecuta `composer install`, `npm ci` y `npm run build`. Copia `.env.example` a `.env`, define credenciales propias de un esquema nuevo y genera la clave con `php artisan key:generate`. Ejecuta las migraciones. Para generar el escenario ficticio, sigue [el procedimiento de laboratorio](../V3_LABORATORIO.md). Conserva las claves fuera de los archivos distribuidos.

Usa el [kit de despliegue](INSTALACION_LIMPIA.md) para preparar el frontal. El ejemplo Nginx de RC1 escucha solamente en loopback y usa PHP-CGI en el mismo equipo. Para un host distinto valida las rutas y los puertos antes de arrancar. Mantén MySQL en loopback. [Respaldo y restauración](BACKUP_RESTAURACION_ROLLBACK.md)

En PowerShell prepara las variables de la instancia de laboratorio y ejecuta:

```powershell
./scripts/frontal-rc1.ps1 -Accion Iniciar -Nginx 'C:/ruta/nginx.exe' -PhpCgi 'C:/ruta/php-cgi.exe'
./scripts/frontal-rc1.ps1 -Accion Estado -Nginx 'C:/ruta/nginx.exe'
./scripts/frontal-rc1.ps1 -Accion Detener -Nginx 'C:/ruta/nginx.exe'
```

Usa una distribución oficial de Nginx. El lanzador necesita un directorio dedicado de Nginx y un esquema de demostración propio. Para Android emulado en el mismo anfitrión, el alias estándar de Android es `10.0.2.2` y el frontal escucha en el puerto 8114. [Descargas oficiales de Nginx](https://nginx.org/en/download.html)

Instala cada APK por separado y verifica su SHA-256. El perfil de laboratorio permite seleccionar el origen. Usa el mismo origen en ambos APK y en el panel. Comprueba `/api/v1/salud` antes de iniciar sesión.

## Alcance

La prerelease distribuye los APK de laboratorio y sus evidencias. La matriz conserva el resultado histórico por revisión. Siguen pendientes la cobertura completa del panel y los dos ensayos integrales.

Para LAN falta confirmar una red privada y un segundo cliente autorizado. Para HTTPS privado falta una red Tailscale autorizada y un cliente en otra conexión. La máquina anfitriona debe permanecer encendida para ambos perfiles. [Intervenciones](INTERVENCIONES_V4_2.md)
