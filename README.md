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

Y un operador de ejemplo con permisos limitados (Avance 05): `operador@rsu.test` / `Operador123`.

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

## Avance 03: grupos de personal y programaciones

- **Grupos de personal** (Programación → Grupos de personal): plantillas con nombre, turno, zona, vehículo, días de la semana, conductor y ayudantes. Los ayudantes que caben salen de la capacidad del vehículo (capacidad − 1 conductor).
- **Programación** (Programación → Programación): una fila por día de trabajo.
  - *Nueva programación*: fecha de inicio, fecha de fin y grupo. Al elegir el grupo trae conductor, ayudantes y días, que se pueden cambiar. **Guardar solo se activa después de «Validar disponibilidad»**; cualquier cambio posterior vuelve a bloquearlo.
  - *Programación masiva*: varios grupos a la vez con un solo rango de fechas (con «Todos» y filtro de grupos). Es la forma rápida cuando hay decenas de grupos.
  - La validación revisa contrato vigente, vacaciones aprobadas, personal o vehículo ya asignado el mismo día y turno, función correcta, capacidad del vehículo y zona/vehículo activos. Los **feriados activos se omiten** (los inactivos no cuentan). Si hay errores se listan por persona con sugerencias de reemplazo.
  - Modificar y eliminar solo aplican a lo «Programado»; **Finalizar** desde el día programado; el **historial de cambios** sale de la bitácora; el listado exporta a Excel, PDF e imprime.
  - Las restricciones únicas de la base de datos impiden duplicar persona o vehículo el mismo día y turno aunque dos usuarios guarden a la vez.
  - Aprobar vacaciones de alguien programado en esas fechas se bloquea hasta reprogramarlo.
- Los datos de demostración (`php artisan db:seed` en local) incluyen «Grupo A» y «Grupo B».
- **Reglas para situaciones reales** (ajustables en `config/proyecto.php`, bloque `programacion`):
  - Una persona puede trabajar dos turnos el mismo día, incluso seguidos; lo único que se rechaza es el mismo turno dos veces o turnos cuyos horarios se cruzan.
    Doble y triple turno se permiten, pero la validación **avisa** (no bloquea) cuando alguien quedaría con más de 16 h seguidas (`proyecto.programacion.max_horas_seguidas`).
  - Los mensajes de la validación dicen quién, qué turno y qué días («Carlos Guerrero tiene vacaciones aprobadas los días 02/12/2026, 04/12/2026 y 07/12/2026.») y proponen qué hacer. Cambiar o desactivar un contrato, o aprobar vacaciones, se bloquea si deja días programados sin cobertura (se resuelve con «Reemplazar»).
  - El resultado de «Validar disponibilidad» se muestra en tarjetas cortas (quién · qué pasa · qué días · solución en una línea) y, cuando hay un reemplazo libre, con un botón «Cambiar por…» que lo aplica al formulario y vuelve a validar solo. La API entrega `problemas` y `notas` estructurados además de los textos.
  - Pruebas combinatorias (lentas, aparte de la suite normal): `php artisan test tests/Combinatorias` cruza cientos de situaciones y compara el validador contra un cálculo independiente.
  - **Reemplazar** (botón en Programación): cambia a un conductor, un ayudante o un vehículo (por ejemplo, al taller) en todas las programaciones pendientes de un rango, con las mismas validaciones y con historial.
  - Un feriado nuevo sobre días ya programados avisa; el listado marca esas filas y «Quitar las de feriados» las elimina de una vez.
  - Aprobar vacaciones de quien está programado se bloquea hasta reemplazarlo; al solicitarlas ya se avisa.
  - El listado marca a quien no registró asistencia en un día que ya empezó (informativo) y la validación avisa de personas con muchas faltas recientes o con contrato que vence pronto.
  - Dos grupos activos del mismo turno y con días en común no pueden compartir conductor, ayudante ni vehículo; el listado de grupos marca a quien hoy no podría trabajar.
  - Cambiar un grupo no modifica lo ya programado (es una plantilla): se avisa y se usa «Reemplazar».
  - Lógica del turno en curso (se mide con el inicio y el fin reales del turno, también el nocturno que cruza la medianoche): no se finaliza un turno que no terminó, no se programa ni se modifica un turno que ya terminó, no se mueve una programación a un turno que ya empezó si el personal no marcó en él, quien entra a cubrir en un turno en curso tiene que haber marcado asistencia en ESE turno, no se elimina una programación si alguien ya marcó asistencia (se finaliza) y no se cambia a quien ya registró su entrada. El dashboard de hoy incluye el turno de noche de ayer mientras siga en curso.
  - Si dos usuarios guardan lo mismo a la vez (misma persona o mismo vehículo, fecha y turno), uno guarda y el otro recibe un mensaje claro para volver a validar.
  - **Primeros pasos**: los formularios que dependen de otros datos (grupos, programaciones, contratos, vacaciones, asistencia, vehículos) avisan qué hay que registrar antes y dónde. Las cantidades se pueden escribir con coma decimal (`1,5`, `1.500,50`); las placas deben combinar letras y números y se guardan en mayúsculas.
  - **Varias personas a la vez**: si dos usuarios guardan lo mismo en el mismo instante, uno guarda y el otro recibe un mensaje claro (no un error 500); las operaciones que se cruzan en la base de datos se reintentan solas, solo una persona puede resolver una solicitud de vacaciones y las zonas se guardan de una en una para que no queden superpuestas.
  - Las vacaciones no cruzan de un año a otro (se dividen en dos solicitudes) y cambiar el horario de un turno con programaciones pendientes avisa.

## Avance 04: modificar programaciones, gestión de cambios y dashboard

Después de `git pull` ejecute `php artisan migrate` (crea `change_reasons` y `programming_changes`) y, en local, `php artisan db:seed --class=CambiosDemoSeeder` (motivos básicos, dos grupos que trabajan todos los días con programaciones desde hoy, una vacación que deja incompleta una programación y cambios individuales y masivos de ejemplo para ver el dashboard y la lista de cambios con datos).

- **Programación masiva** (Programación → Programación → «Programación masiva»): rango de fechas, filtro de grupos por turno y por texto. Los **feriados del rango se listan** y el usuario marca cuáles quiere trabajar (los demás se omiten). Cada grupo tiene **Editar** (conductor, vehículo y ayudantes solo para esa programación) y **Detalles del grupo** (su personal y su historial de cambios individuales y masivos). «Validar disponibilidad» aplica la validación individual **grupo por grupo** y marca cada tarjeta («Sin problemas · 6 días» / «2 problemas»); el grupo con problemas se abre solo y el botón «Cambiar por…» lo corrige.
- **Modificar programación**: tres paneles (turno, vehículo, personal) con el valor actual y el nuevo. **Cada cambio pide su motivo**: uno predefinido y/o un texto. Debajo, la tabla de cambios de esa programación, donde cada uno se puede **deshacer**. Al cambiar algo, la programación pasa a **Reprogramado** (sigue siendo pendiente: se puede modificar, eliminar y finalizar).
- **Dashboard** (Programación → Dashboard): para una fecha y turno, total de programaciones, **completas, incompletas y personal faltante**, con una tarjeta por zona. «Ver detalles» lista cada programación con su personal; el faltante (vacaciones, sin contrato, faltó o sin asistencia con el turno ya iniciado) se cambia por alguien **disponible**: la lista solo permite a quien no esté de vacaciones, tenga contrato, no tenga ese turno (ni uno que se cruce) y, si el turno de hoy ya empezó, haya marcado asistencia.
- **Diseño**: las pantallas del Avance 04 usan el mismo lenguaje visual que el resto del sistema (paleta RSU pizarra/esmeralda/ámbar, tarjetas `t-tarjeta`, insignias `t-insignia`, botones `t-boton`, `x-admin.kpi-card`, tarjetas de problema de la validación). El dashboard muestra cuatro indicadores, filtros y una tarjeta por programación con «Presentes / Faltantes» y «Ver detalles»; «Ver detalles» abre el *Editor de programación* (turno, vehículo y personal, cada cambio con su motivo y la tabla «Cambios registrados»). La programación masiva muestra una tarjeta por grupo con selects de conductor y ayudantes, filtro por turno, feriados a omitir y resultado por grupo. El cambio masivo pide confirmación con un resumen y cada cambio tiene su ventana de detalle. Todas las listas tienen buscador con lupa y «Mostrar N registros», y todo se adapta al móvil (las ventanas pasan a pantalla completa).
- **Gestión de cambios**:
  - *Motivos*: CRUD de motivos (nombre y descripción). Un motivo eliminado no borra el historial: los cambios conservan su nombre.
  - *Cambios*: lista con filtros (fecha inicio/fin y tipo), detalle con valores previos y nuevos (programación, usuario, fecha, motivo) y **cambio masivo** en un rango de fechas y zona: turno, vehículo, conductor u ocupante, con validación previa y confirmación.
  - Los cambios de una misma modificación comparten lote: en la lista se ven como una fila («Varios» si mezclan tipos) y se deshacen juntos; desde la ventana de modificar se puede deshacer un solo cambio.
  - Todo cambio pide motivo, también el antiguo «Reemplazar»; para solo validar la disponibilidad no hace falta.
  - **Eliminar un cambio restablece los valores iniciales** (todo o nada): solo se puede si la programación sigue pendiente, no hay un cambio posterior del mismo dato y el valor anterior sigue disponible; si ya no queda ningún cambio, la programación vuelve a «Programado».

## Producción y operación

- **Entorno**: `.env.example` viene en modo `local`. En producción use `APP_ENV=production`, `APP_DEBUG=false` y cookies seguras (`SESSION_SECURE_COOKIE` se activa solo).
- **Administrador inicial**: en `local` se crea `admin@rsu.test` / `Admin12345` junto con datos de demostración. En producción el seeder exige `ADMIN_EMAIL` y `ADMIN_PASSWORD` (contraseña fuerte) y solo carga catálogos (funciones, turnos, ubigeo).
- **Tareas programadas**: el cierre de contratos vencidos (`contratos:cerrar-vencidos`) corre cada día a las 00:05. Agregue al cron: `* * * * * php artisan schedule:run`.
- **Recuperar acceso**: `php artisan admin:cambiar-clave correo@dominio` restablece la contraseña desde la consola. La recuperación por correo solo se ofrece si `MAIL_MAILER` no es `log`.
- **Gestión de usuarios (Avance 05)**: lista con foto, DNI, tipo y estado; alta y edición en ventana con todos los datos; tipo **Administrador** (acceso total, siempre queda uno activo) u **Operador** (solo lo que se le conceda). La pantalla **Permisos** (llave de cada fila) marca qué puede ver, crear, editar o eliminar en cada módulo (76 permisos `recurso.accion`); el menú y todas las rutas del panel respetan esos permisos. Un operador solo puede conceder permisos que él mismo tiene y no puede tocar cuentas de administradores.
- **Pruebas y estilo**: `php artisan test` y `vendor/bin/pint`; el flujo de GitHub Actions (`.github/workflows/pruebas.yml`) ejecuta ambos.

## Docker: ejecutar el sistema en otra computadora

El sistema corre en **4 contenedores** (4 imágenes distintas) definidos en `docker-compose.yml`:

| Servicio | Imagen | Función |
|---|---|---|
| `web` | `nginx:1.27-alpine` (oficial) | Proxy inverso, recibe las visitas en el puerto 8080 |
| `app` | `pieroramirezusat/rsu-municipal` (propia, Docker Hub) | Laravel 12 + PHP 8.3 + Apache, construida con el `Dockerfile` |
| `db` | `mysql:8.0` (oficial) | Base de datos con volumen persistente |
| `phpmyadmin` | `phpmyadmin:5` (oficial) | Administración de la base, solo en `127.0.0.1:8081` |

Imagen en Docker Hub: https://hub.docker.com/r/pieroramirezusat/rsu-municipal (etiquetas `1.0`, `1.1` y `latest`). La `1.1` todavía no incluye el dashboard medido por turno, las tarjetas como filtro ni la bitácora legible: se publicarán en la `1.2`.

### Requisitos
- Docker Desktop (Windows/Mac) o Docker Engine (Linux) con **Docker Compose 2.23.1 o superior**.
- Conexión a internet para descargar las imágenes. Una vez descargadas, el sistema funciona sin conexión (AdminLTE, Bootstrap y Leaflet vienen dentro de la imagen); solo el fondo del mapa de Zonas y el buscador de direcciones consultan OpenStreetMap.
- Puertos libres: 8080 y 8081 (se pueden cambiar con `WEB_PORT` y `PMA_PORT`).

### Pasos (no hace falta instalar PHP, MySQL ni Composer)
1. Descargue el proyecto (`git clone` o ZIP) y abra una terminal en su carpeta. El repositorio es privado: para clonarlo hay que tener acceso de colaborador. En Windows, ejecute antes `git config --global core.longpaths true`; si no, el clon falla con «Filename too long».
2. Cree el archivo de variables con claves aleatorias:
   - Windows (PowerShell): `powershell -ExecutionPolicy Bypass -File docker\crear-env.ps1`
   - Linux / macOS / Git Bash: `sh docker/crear-env.sh`

   Muestra una sola vez la contraseña del administrador; también queda en el archivo `.env`.
   (Alternativa manual: copie `.env.docker.example` a `.env` y cambie todos los valores `CAMBIAR_...`; el sistema no arranca con ellos sin cambiar, ni con `ADMIN_PASSWORD` vacía.)
3. Levante el sistema: `docker compose up -d` (la primera vez descarga la imagen desde Docker Hub; la base se crea y se llena sola).
4. Abra http://localhost:8080 e ingrese con `admin@rsu.local` y la contraseña del paso 2. phpMyAdmin: http://localhost:8081 (usuario `rsu` y la contraseña `DB_PASSWORD` del `.env`).

Si ya tiene un `.env` de desarrollo en la carpeta, el script no lo sobrescribe: use otro archivo, `docker\crear-env.ps1 -Destino .env.docker`, y luego `docker compose --env-file .env.docker up -d`.

### Si no puede iniciar sesión
- Vea con qué datos se creó el administrador: `docker exec rsu-app printenv ADMIN_EMAIL ADMIN_PASSWORD`.
- Ponga una contraseña nueva sin borrar datos: `docker exec -it rsu-app php artisan admin:cambiar-clave admin@rsu.local`.
- Cambiar `ADMIN_PASSWORD` en el `.env` después del primer arranque no surte efecto: el administrador se crea una sola vez.

### Comandos útiles
- Estado: `docker compose ps` · Registros: `docker compose logs -f app`
- Detener: `docker compose down` (conserva los datos) · Borrar todo, datos incluidos: `docker compose down -v`
- Construir la imagen en vez de descargarla: `docker compose build app`

Seguridad: la imagen no contiene `.env` ni contraseñas; se reciben por variables de entorno y el archivo `.env` está fuera de Git. Las cookies de sesión no son `Secure` por defecto (`SESSION_SECURE_COOKIE=false`) para que el login funcione por http desde cualquier dirección; si se publica el sitio con HTTPS, definir `SESSION_SECURE_COOKIE=true` en el `.env`.

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
