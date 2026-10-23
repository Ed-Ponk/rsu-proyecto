# Modificar con seguridad

## Preparar el trabajo

Ejecute `git status`, cree una rama con `git switch -c codex/mi-cambio` y use una base local con copia de seguridad. Lea el módulo y sus pruebas antes de editar. No comparta `.env`, sesiones ni bases de datos.

## Agregar un campo a Modelos

1. Cree una migración nueva: `php artisan make:migration agregar_observacion_a_brandmodels --table=brandmodels`. El nombre es un ejemplo de cambio futuro; ese archivo no está incluido.
2. Defina tipo, longitud y tratamiento de registros existentes. Una columna obligatoria necesita una estrategia para los datos anteriores.
3. Revise `$fillable` en `app/Models/Modelo.php` si el campo se asignará mediante `create` o `update`.
4. Añada la regla a `ModeloController::reglas()` y el mensaje cuando corresponda. No acepte datos sin validarlos.
5. Añada el campo a `resources/views/admin/modelos/_form.blade.php` usando una etiqueta asociada y `old()` para recuperar el valor.
6. Si aparece en el listado, actualice `fila()`, el encabezado de la tabla y las columnas de `public/js/tads/catalogos.js` juntos. Escape todo texto al convertirlo en HTML.
7. Ajuste el seeder solo si necesita un dato de ejemplo; añada una prueba de aceptación y una de rechazo.
8. Aplique `php artisan migrate` únicamente en la base prevista. Ejecute las pruebas y revise la pantalla.

## Cambiar una pantalla o crear otra

Reutilice `x-admin.cabecera`, los componentes de `resources/views/components/admin/` y el gestor de formularios cuando encajen. Una ruta HTML debe devolver una vista; una ruta de datos debe usar una URL distinta y devolver JSON. Ponga las rutas fijas antes del `resource` y conserve middleware, nombres y respuestas de los consumidores existentes.

Para agregar una funcionalidad, primero trace ruta → controlador → validación → modelo/servicio → vista → pruebas. Un servicio se justifica cuando organiza una regla real, no por tener una carpeta más. No hace falta crear una interfaz ni un repositorio para una operación de Eloquent.

## Cambiar estilos

El panel carga `public/css/tads.css` y `tads-menu.css`; las pantallas de acceso cargan `tads-login.css`. Compruebe escritorio, móvil, teclado, mensajes y modales. El perfil utiliza assets Vite: ejecute `npm run build`. Si el navegador conserva CSS antiguo, haga una recarga completa.

## Comprobaciones

```bash
php artisan test --filter=NavegacionTest
php artisan test
php vendor/bin/pint --test
npm run build
php artisan route:list -v
git diff --check
```

Las pruebas usan SQLite en memoria, definido en `phpunit.xml`; requieren `pdo_sqlite`. Antes de ejecutarlas, quite configuraciones cacheadas con `php artisan config:clear` si las hubiera y confirme la configuración de pruebas. MySQL tiene diferencias de integridad y concurrencia: pruebe también sobre una copia local MySQL antes del despliegue.

## Revertir

Revise `git diff` antes del commit. Para revertir un commit ya compartido, use `git revert ID_DEL_COMMIT` (reemplace el identificador). Revertir código no recupera datos eliminados. Una migración inversa puede borrar columnas: inspeccione `down()` y prepare respaldo antes de ejecutarla. No use `migrate:fresh` sobre una base con información que necesite conservar.
