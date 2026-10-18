<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Instalación local (rama `ramirez-alternativa`)

Esta rama usa **MySQL** (o MariaDB) como base de datos.

### Requisitos

- PHP 8.2 o superior, con la extensión `pdo_mysql` activa (XAMPP y Laragon ya la traen)
- Composer
- Node.js 20 o superior
- MySQL 8 o MariaDB 10.4 o superior (por ejemplo, el de XAMPP o Laragon)
- Git

### Pasos

1. Clonar el repositorio en esta rama:

   ```bash
   git clone -b ramirez-alternativa https://github.com/Ed-Ponk/rsu-proyecto.git
   cd rsu-proyecto
   ```

   Si ya tienes el repositorio clonado, cambia a la rama:

   ```bash
   git fetch origin
   git checkout ramirez-alternativa
   ```

2. Instalar las dependencias de PHP y de JavaScript:

   ```bash
   composer install
   npm install
   ```

3. Crear el archivo de entorno y generar la clave de la aplicación:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   En Windows (CMD) usa `copy .env.example .env` en lugar de `cp`.

4. Crear la base de datos vacía en MySQL (desde phpMyAdmin, MySQL Workbench o la consola):

   ```sql
   CREATE DATABASE rsu_proyecto CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

5. Configurar la conexión en tu `.env` con los datos de **tu** MySQL:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=rsu_proyecto
   DB_USERNAME=root
   DB_PASSWORD=
   ```

   - XAMPP y Laragon usan por defecto el puerto `3306`, el usuario `root` y la contraseña vacía.
   - Si tu MySQL usa otro puerto o tiene contraseña, cámbialos aquí.
   - El `.env` es personal y **no se sube a GitHub**.

6. Ejecutar las migraciones y los datos iniciales:

   ```bash
   php artisan migrate --seed
   ```

7. Compilar los assets y levantar el servidor:

   ```bash
   npm run dev
   php artisan serve
   ```

   Abre <http://127.0.0.1:8000> en el navegador.

### Configuración local del .env

El `.env.example` viene preparado para producción (`APP_ENV=production`, `APP_DEBUG=false`). En desarrollo, edita tu `.env` y usa:

```env
APP_ENV=local
APP_DEBUG=true
```

### Usuarios de prueba (solo desarrollo)

El seeder crea un usuario administrador inicial:

- Email: `admin@rsu.test`
- Contraseña: `Admin12345`

**Es solo para entorno de desarrollo.** No usar en producción: cambia la contraseña apenas inicia sesión (o elimina el usuario) antes de desplegar.

Para probar la **Marcación DNI** (menú *Gestión de personal → Marcación DNI*), todo el personal del seeder tiene la clave `secret123`:

| DNI | Persona | Contrato vigente |
|---|---|---|
| `12345678` | Carlos Guerrero (Conductor) | Sí |
| `70000008` | Fernando Ramírez (Conductor) | Sí |
| `80000019` | Miguel Gómez (Ayudante) | Sí |
| `70000007` | Juan Pérez (Ayudante) | No: el sistema rechaza la marcación |

Reglas de la marcación:

- Una entrada y una salida por turno.
- La entrada registra la tardanza si se pasa de la tolerancia.
- La espera mínima entre marcaciones, la tolerancia y los demás parámetros se ajustan en `config/proyecto.php`.

### Después de actualizar la rama (`git pull`)

Si llegaron cambios nuevos, aplica las migraciones pendientes:

```bash
composer install
php artisan migrate
```

Para empezar de cero con los datos de prueba (**borra todo lo que tengas en la base**):

```bash
php artisan migrate:fresh --seed
```

### Pruebas automáticas (opcional)

```bash
php artisan test
```

Usan SQLite en memoria, así que no tocan tu base MySQL. Las pruebas de imágenes de vehículos necesitan la extensión `gd` de PHP. Si no la tienes activa, esas 3 pruebas fallan, pero el resto funciona igual.

### Problemas comunes

| Error | Solución |
|---|---|
| `could not find driver` | Activa `extension=pdo_mysql` en tu `php.ini` y reinicia el servidor. |
| `Access denied for user 'root'` | El usuario o la contraseña de `DB_USERNAME` / `DB_PASSWORD` no coinciden con tu MySQL. |
| `Unknown database 'rsu_proyecto'` | Falta el paso 4: crear la base de datos. |
| `SQLSTATE[HY000] [2002]` (conexión rechazada) | MySQL no está encendido, o el puerto de `DB_PORT` no es el correcto. |
| Los cambios de configuración no se aplican | Ejecuta `php artisan config:clear`. |
| La página se ve sin estilos nuevos | Recarga con <kbd>Ctrl</kbd> + <kbd>F5</kbd>. |

### Assets de AdminLTE (`public/vendor`)

Los assets estáticos de AdminLTE y sus dependencias (bootstrap, fonts, overlayscrollbars) están versionados en `public/vendor`. No se regeneran automáticamente al instalar (`composer install` no ejecuta ningún `vendor:publish` de AdminLTE). Si en el equipo prefieren regenerarlos en lugar de versionarlos, ejecuta:

```bash
php artisan adminlte:install --only=assets --only=vendor_assets
```

### Avance 02: vacaciones, turnos, zonas y feriados

Nuevas pantallas en el menú lateral:

- **Gestión de personal → Vacaciones**: solicitudes con aprobar / rechazar / editar / eliminar. Solo el personal con contrato **nombrado** o **permanente** puede pedirlas, las fechas no pueden cruzarse con otras solicitudes pendientes o aprobadas, y solo las aprobadas descuentan días del cupo anual (30 por defecto, `config/proyecto.php` → `vacaciones.dias_por_anio`). El personal con vacaciones aprobadas deja de estar disponible para las programaciones (`Personal::disponibles($fecha)`).
- **Programación → Turnos**: CRUD de turnos (los nocturnos terminan al día siguiente). Dos turnos no pueden ocupar las mismas horas.
- **Programación → Zonas**: CRUD con perímetro dibujado en un mapa de **OpenStreetMap** (Leaflet + Leaflet.draw, sin costo ni clave). Incluye el visor del perímetro de cada zona y el botón **Ver mapa de zonas**. Departamentos, provincias y distritos salen de `config/ubigeo.php` (hoy Lambayeque; para agregar otro departamento se copia la misma estructura). Un perímetro necesita mínimo 3 coordenadas dentro de Perú y no puede cruzarse consigo mismo.
- **Programación → Feriados**: CRUD con tarjetas de resumen y filtros. El botón **Cargar Feriados Perú** consulta la API pública [Nager.Date](https://date.nager.at) (sin clave) y, si no hay internet, usa el calendario propio del sistema (`App\Services\CargadorFeriados`); no duplica los que ya existen.

Restricciones principales (todas se validan en el servidor y tienen pruebas en `tests/Feature/Avance02RestriccionesTest.php`):

- **Vacaciones:** datos obligatorios; solo personal activo con contrato nombrado o permanente vigente; fecha de inicio no pasada; no se cruzan con solicitudes pendientes/aprobadas de la misma persona (las rechazadas liberan las fechas); máximo el cupo anual restante; solo las pendientes se editan o eliminan (al editar se vuelven a aplicar todas las reglas); no se aprueba un periodo ya terminado ni a quien perdió el derecho; quien está de vacaciones aprobadas no puede registrar asistencia (manual ni con DNI); el personal con vacaciones no se puede eliminar (se marca inactivo).
- **Turnos:** nombre único, horas distintas, duración entre 1 y 12 horas, sin cruces con otros turnos, no se elimina si tiene asistencias.
- **Feriados:** fecha única entre 2020 y 10 años adelante, descripción mínima de 3 caracteres, filtros con rango válido; los inactivos no cuentan en las validaciones.
- **Zonas:** nombre único; mínimo 3 coordenadas dentro de Perú y del departamento elegido; sin puntos repetidos ni lados que se crucen; superficie entre 100 m² y 150 km²; no puede invadir otra zona (activa o inactiva); el distrito debe pertenecer a la provincia y esta al departamento.

Notas:

- Leaflet y Leaflet.draw están en `public/vendor/leaflet` y `public/vendor/leaflet-draw` (no hace falta instalarlos). Los mosaicos del mapa y el buscador de direcciones necesitan conexión a internet.
- Tras actualizar la rama ejecuta `php artisan migrate` (o `migrate:fresh --seed` para recargar los datos de ejemplo: 3 vacaciones, 16 feriados del año y 5 zonas en José Leonardo Ortiz).

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
