# Primer recorrido por RSU

RSU ayuda a la municipalidad a administrar vehículos, personal, contratos, asistencia y zonas de recolección. No calcula todavía recorridos de camiones: no confunda las zonas dibujadas con un planificador de rutas.

Laravel es el conjunto de herramientas PHP con el que está construida la aplicación. Evita tener que escribir desde cero sesiones, consultas y validaciones. Las carpetas conservan sus convenciones para poder consultar su documentación y usar Artisan.

## Aprender con la pantalla Modelos

1. El navegador solicita `/admin/modelos`. Una **ruta** es la asociación entre una dirección y el código que la atiende: está en `routes/web.php`.
2. Un **middleware** comprueba condiciones antes de ejecutar ese código. El panel requiere sesión y el permiso `administrar-panel`, definido en `app/Providers/AppServiceProvider.php`: cuenta activa sin tipo de personal.
3. Un **controlador** recibe la solicitud. `app/Http/Controllers/Admin/ModeloController.php`, método `index()`, prepara la respuesta.
4. Una **vista Blade** es una plantilla HTML con instrucciones PHP. `resources/views/admin/modelos/index.blade.php` dibuja la pantalla; la tabla obtiene sus filas desde `/admin/modelos/datos`.
5. Un **modelo** representa registros de una tabla. `app/Models/Modelo.php` utiliza `brandmodels` y se relaciona con `Marca` y `Vehiculo`.
6. El formulario vive en `_form.blade.php`, dentro de la misma carpeta de vistas. Al guardar, `store()` valida los datos y crea el registro. Al editar, `update()` también impide cambiar la marca si ya hay vehículos asociados.

Una **validación** comprueba que el dato sea admisible: por ejemplo, el código de un modelo debe ser único dentro de su marca. Las reglas están en `ModeloController::reglas()`, compartidas con la comprobación en vivo `verificar()`. No basta con un mensaje del navegador: el servidor vuelve a validar.

Un **servicio** contiene reglas o procesos compartidos que requieren más explicación. `app/Services/ReglasContrato.php` comprueba fechas y condiciones de contratación; `ServicioAsistencia.php` organiza la marcación. No cada operación necesita un servicio nuevo.

Un **componente** es una pieza de interfaz que se reutiliza. `resources/views/components/admin/cabecera.blade.php` reúne título, descripción y acción principal. Cambiarlo afecta a varias pantallas.

Una **migración** describe un cambio de estructura de la base de datos. Se guarda en `database/migrations/`. Para agregar una columna se crea una migración nueva; no se cambia una histórica ya aplicada en otro equipo.

## Por dónde continuar

Lea [el mapa](MAPA_DEL_PROYECTO.md), busque su módulo en [la guía de módulos](GUIA_DE_MODULOS.md) y siga [un cambio pequeño](GUIA_DE_MODIFICACIONES.md). Antes de tocar datos, aprenda a distinguir el entorno de desarrollo del de producción. Las reglas de contratos, geometría y concurrencia requieren conocimientos básicos de programación y pruebas.
