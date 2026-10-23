# Mapa del proyecto

Punto de entrada actualizado: [implementación y organización](IMPLEMENTACION_Y_VERIFICACION.md) y [tres recorridos del código](TRES_RECORRIDOS_DEL_CODIGO.md).

Revisión del 23/10/2026: [mapa técnico actual](MAPA_TECNICO_ACTUAL.md), [análisis de validaciones y concurrencia](ANALISIS_VALIDACIONES_Y_CONCURRENCIA.md) e [inventario de archivos, métodos y pruebas](inventario-proyecto.json).

Las rutas indicadas en estas guías son relativas a la raíz del repositorio.

```text
app/
  Actions/Fortify/           Acceso y cambios de perfil/contraseña
  Actions/Jetstream/         Eliminación protegida de cuenta
  Console/Commands/          Diagnóstico de integridad sin escrituras
  Http/Controllers/Admin/    Entradas HTTP y respuestas por módulo
    Concerns/               Respuestas y archivos compartidos
  Http/Requests/Admin/       Validación de flujos complejos
  Http/Middleware/          Transacciones, versiones, acceso y cabeceras
  Models/                   Relaciones, consultas y datos
    Concerns/               Auditoría y versiones
  Services/                 Reglas de negocio, asistencia, galería y seguridad
  Support/                  Bloqueos e identidad inexistente
config/                     Negocio, menú, librerías y servicios Laravel
resources/
  views/admin/              Vistas por módulo
  views/layouts/admin       Entrada compartida del panel mediante Vite
  views/components/         Componentes del panel y Jetstream
  js/admin/compartido/       Peticiones, errores, buscadores, reloj, tablas
  js/admin/modulos/          Un archivo por módulo
  js/admin/panel.js          Orden de carga
  css/admin/                Fuentes de panel, menú y acceso
public/                     Entrada web, terceros, imágenes y cargas
  build/                    Assets compilados; no editar
routes/                     Rutas HTTP y comandos programados
database/                   Migraciones, seeders y factory
tests/
  Feature/Negocio/           Reglas y flujos
  Feature/Seguridad/         Acceso y perfil
  Feature/Navegacion/        Páginas y endpoints
  Feature/Arquitectura/      Contratos de estructura y componentes
  Concurrencia/             Dos procesos sobre MySQL descartable
scripts/                    Generación reproducible de inventario
docs/                       Guías y evidencia de implementación
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
| Tabla o modal | `resources/js/admin/compartido/`, `resources/js/admin/modulos/` | Lógica compartida y columnas específicas |
| Apariencia | `resources/css/admin/` | El panel utiliza CSS público directamente |
| Perfil Jetstream | `resources/css/app.css`, `resources/js/app.js` | Se compilan con Vite |
| Base de datos | `database/migrations/`, `app/Models/` | Estructura y relaciones |
| Parámetros de negocio | `config/proyecto.php` | Cupos, tolerancias y límites existentes |
| Comprobar un cambio | `tests/Feature/` | Pruebas sobre SQLite en memoria |

No edite `vendor/`, `node_modules/`, `public/build/` ni archivos de caché. Se generan a partir del código y las dependencias.
