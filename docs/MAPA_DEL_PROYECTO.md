# Mapa del proyecto

Las rutas indicadas en estas guías son relativas a la raíz del repositorio.

```text
app/
  Actions/Fortify/         Creación de cuentas y cambios de contraseña/perfil
  Actions/Jetstream/       Eliminación protegida de cuentas
  Http/Controllers/Admin/  Solicitudes de cada módulo administrativo
    Concerns/             Respuestas AJAX y buscadores reutilizados
  Http/Middleware/         Cabeceras HTTP de seguridad y caché
  Http/Responses/          Recuperación de acceso sin revelar cuentas
  Models/                  Tablas, relaciones y operaciones de datos
    Concerns/             Registro compartido en bitácora
  Providers/               Autenticación, permiso del panel y configuración
  Services/                Reglas de contratos, vacaciones, asistencia y zonas
  Support/                 Verificación ficticia para igualar tiempos de acceso
config/                    Parámetros del negocio, menú y servicios Laravel
database/
  factories/               Cuentas para pruebas automatizadas
  migrations/              Historia de la estructura SQL
  seeders/                 Catálogos y ejemplos según el entorno
public/
  css/                     tads.css, tads-menu.css y tads-login.css
  js/gestor.js              Tablas, ventanas y formularios compartidos
  js/tads/                 Comportamiento específico por módulo
  vendor/                  Assets de terceros publicados; no código propio
resources/views/
  admin/                   Una carpeta por módulo: index, create, edit y _form
  admin/partials/          Adaptadores y fragmentos compartidos
  components/admin/       Componentes visuales reutilizables
  auth/                   Pantallas de acceso
  profile/                Gestión de la cuenta autenticada
routes/web.php             Interfaces, datos JSON y operaciones del panel
routes/console.php         Comandos y tareas programadas
tests/Feature/             Pruebas HTTP, componentes y reglas de negocio
tests/Unit/                Pruebas unitarias
docs/                      Guías en español y resultados de revisión
```

| Quiero modificar… | Debo revisar… | Motivo |
|---|---|---|
| Una pantalla | `resources/views/admin/{modulo}/` | HTML y campos reales |
| El formulario de modelos | `resources/views/admin/modelos/_form.blade.php` | Crear y editar comparten campos |
| Validación de modelos | `app/Http/Controllers/Admin/ModeloController.php` | `reglas`, `normalizar` y `mensajes` |
| Guardado | Métodos `store` y `update` del controlador | Reciben y validan antes de escribir |
| Permiso del panel | `app/Providers/AppServiceProvider.php`, `routes/web.php` | Gate y aplicación a las rutas |
| Inicio de sesión | `app/Providers/FortifyServiceProvider.php` | Comprueba identidad, estado y contraseña |
| Menú | `config/adminlte.php` | Define enlaces y agrupaciones |
| Tabla o modal | `public/js/gestor.js`, `public/js/tads/` | Lógica compartida y columnas específicas |
| Apariencia | `public/css/` | El panel utiliza CSS público directamente |
| Perfil Jetstream | `resources/css/app.css`, `resources/js/app.js` | Se compilan con Vite |
| Base de datos | `database/migrations/`, `app/Models/` | Estructura y relaciones |
| Parámetros de negocio | `config/proyecto.php` | Cupos, tolerancias y límites existentes |
| Comprobar un cambio | `tests/Feature/` | Pruebas sobre SQLite en memoria |

No edite `vendor/`, `node_modules/`, `public/build/` ni archivos de caché. Se generan a partir del código y las dependencias.
