# Decisiones técnicas

## Mantener Laravel reconocible

Los controladores ya están agrupados en `Admin`, las vistas por módulo y las reglas extensas en servicios. Se mantiene esa arquitectura: cambiar namespaces o agrupar cada módulo en una carpeta ajena a las convenciones añadiría configuración y saltos sin resolver un problema demostrado. No se renombraron tablas, columnas ni migraciones históricas.

## Separar páginas de datos sin romper clientes

Se agregan rutas `admin.{modulo}.datos` antes de los `resource`. Las 15 tablas apuntan allí. Se reutiliza `index()` para preparar las mismas filas, evitando duplicar consultas o reglas. La condición distingue el nombre de ruta y conserva `ajax()` para clientes antiguos. Las respuestas incluyen `Vary: Accept, X-Requested-With`; el panel utiliza `Cache-Control: private, no-store`.

La transición mantiene negociación en los listados antiguos. Retirarla exigiría confirmar todos los consumidores externos y anunciar el cambio. El contrato nuevo `/datos` siempre es JSON en una respuesta satisfactoria; errores de autenticación/validación siguen la negociación HTTP de Laravel.

## Permisos en cada solicitud

Fortify ya impedía iniciar sesión al personal y a cuentas inactivas. Eso no garantizaba revocar una sesión previamente abierta. La Gate `administrar-panel` aplica la misma regla de identidad en todo `/admin`, incluyendo JSON y escrituras. No se inventan roles adicionales. La factory declara el estado activo igual que el valor predeterminado SQL para representar correctamente una cuenta recién creada en pruebas.

## Reutilizar componentes existentes

El partial `admin.partials.cabecera` ahora llama a `x-admin.cabecera`. Los includes antiguos siguen funcionando y el diseño se modifica en un solo componente. Es una reducción localizada de duplicación; no se creó otra capa de interfaz.

## Validaciones próximas a su uso

Modelos comparte `reglas()` entre guardado y comprobación en vivo; separarlo en múltiples clases añadiría archivos sin eliminar duplicación. Se mantienen reglas locales en controladores y reglas complejas de dominio en `ReglasContrato`, `ReglasVacacion` y `ReglasZona`. Use Form Requests cuando la validación crezca y su extracción permita seguir mejor el flujo.

## Rendimiento con evidencia

Modelos ya usa `with('marca')` y `withCount('vehiculos')`, evitando una consulta por fila en el listado. Asistencias ya tiene paginación en servidor y límite de compatibilidad. No se añadió caché de datos personales ni una capa de consultas nueva sin mediciones. Los demás catálogos siguen usando paginación del cliente: mida volumen y memoria antes de migrarlos a paginación de servidor.
