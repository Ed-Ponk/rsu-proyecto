# Navegación, respuestas y caché

## Evidencia del defecto original

Antes del cambio, `ModeloController::index()` devolvía HTML o JSON en `/admin/modelos` según `request()->ajax()`. `resources/views/admin/modelos/index.blade.php` apuntaba `data-url-listado` a esa misma URL; `Tads.peticion` en `public/js/gestor.js` enviaba `X-Requested-With: XMLHttpRequest` y `Accept: application/json`. `CabecerasDeSeguridad` no declaraba `Vary` para distinguir representaciones.

Esto permitía almacenar una respuesta JSON bajo la dirección usada para navegar a la página. Es un defecto verificable de separación y caché, compatible con el síntoma al restaurar historial. No se capturó una reproducción del fallo intermitente antes de modificarlo: no se afirma que todos los navegadores lo reproduzcan ni que sea la única causa posible.

Se revisaron `routes/web.php`, `bootstrap/app.php`, controladores y JavaScript propio. No existe `routes/api.php` ni se registra uno en el bootstrap. No se encontraron usos de `pushState`, `replaceState` o `popstate` en `resources` y `public/js`. El gestor utiliza `fetch`; los formularios cargan en modales y mantienen el historial normal del navegador.

## Contratos actuales

| Solicitud | Respuesta satisfactoria |
|---|---|
| GET `/admin/modelos` en navegación normal | HTML de Modelos |
| GET `/admin/modelos/datos` | JSON con `data`, incluso sin cabecera AJAX |
| GET `/admin/modelos` con `X-Requested-With: XMLHttpRequest` | JSON antiguo, por compatibilidad |
| GET `/admin/modelos/create` o `/{modelo}/edit` | Formulario HTML |
| POST/PUT/DELETE con AJAX o Accept JSON | Resultado JSON o error 422/404 según el caso |
| POST/PUT/DELETE desde formulario normal | Redirección al listado tras éxito |
| Sin sesión | Redirección a login en HTML; 401 si se solicita JSON |
| Sesión sin permiso del panel | 403 |

La separación se aplica a colores, marcas, modelos, tipos de vehículo, vehículos, tipos de personal, personal, contratos, asistencias, vacaciones, usuarios, bitácora, turnos, zonas y feriados. Los segmentos con guiones usan nombres de ruta sin guiones (`tiposvehiculo`, `tipospersonal`). Consulte `php artisan route:list -v` para las URLs exactas.

## Filtros y páginas

DataTables conserva sus parámetros y formato. Los catálogos filtran/paginan sus filas en el cliente; asistencias puede enviar `draw`, `start` y `length` al servidor. Los filtros específicos siguen viajando como query string al endpoint `/datos`. El cambio no incorpora persistencia de filtros al abandonar una página.

## Pruebas reproducibles

`php artisan test --filter=NavegacionTest` verifica acceso, HTML/JSON de los 15 módulos, cabeceras, compatibilidad AJAX, validación, redirección y CRUD de Modelos, paginación de asistencias y filtros inválidos de feriados. Solicitudes HTTP consecutivas no simulan el historial real.

Prueba manual de navegador: entrar en Modelos, buscar `Atego`, abrir Marcas por el menú, pulsar Atrás, Adelante y recargar. Deben aparecer títulos y tablas HTML. Regresar a Modelos y abrir la página 2. Comprobar también un formulario modal y su cierre. Repita en los navegadores de los usuarios antes de desplegar. Los resultados de esta sesión están en [el informe](INFORME_MODERNIZACION.md).
