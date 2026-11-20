# Auditoría inicial

| Referencia | Hallazgo |
| --- | --- |
| `main` en `ed1812f` | Laravel 12, PHP ≥8.2, Blade, Livewire, Jetstream/Fortify, Sanctum, AdminLTE, Vite. SQLite en el arranque documentado y migraciones Eloquent. |
| `routes/api.php`, `bootstrap/app.php` en main | Existía una ruta de perfil en archivo, pero el arranque no registraba el archivo de rutas API. |
| `routes/web.php` en main | Panel autenticado con sesión y control de ingreso del personal. ABM de catálogos, vehículos, personal, contratos y asistencias. |
| `origin/avance-06-api` en `842c295` | Descendiente directo en el grafo de main, 26 commits. Incorpora zonas, permisos, programación, recorridos, API de conductor, Docker y cobertura operativa. |
| Otras referencias remotas | `ramirez-alternativa`, `prueba-alternativa`, `codex/validaciones-concurrencia-estructura`, `fix/logo-sidebar-colapsado`. |
| Actores | Administrador y operador en el panel, personal conductor y ayudante, consulta ciudadana introducida en esta rama. |
| Integraciones en el avance | Geocodificación y mapas del panel, carga de feriados y correo configurable para recuperación. Su disponibilidad externa requiere validación operativa. |

La copia inicial quedó limpia en `ed1812f`. Se instalaron las dependencias fijadas, se migró una SQLite nueva y se cargaron los datos del seeder local. La suite Feature original dio 36 aprobadas, 6 omitidas y 98 aserciones. La primera ejecución sobre `842c295` antes de cambiar sus pruebas dio 181 casos, 5 fallos, 6 omitidos y 1 incompleto. Los fallos correspondían a campos de usuario ausentes en fixtures, un texto de inicio retirado y fechas de vacaciones que cruzaban el cierre anual.

GitHub informó permiso de escritura para la identidad autenticada. La rama de trabajo recibió los cambios operativos mediante fast-forward. La referencia `main` conserva el commit inicial.
