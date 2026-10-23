# Diseño del sistema RSU

Aplicación de trabajo municipal: localizar registros, comprobar datos y guardar cambios con claridad. Se conserva el logotipo oficial y la tipografía local; los cambios de esta revisión se concentran en Inicio, acceso y componentes compartidos.

## Paleta y significado

| Uso | Valor / token |
|---|---|
| Texto principal y navegación | `#0f172a`, `--rsu-slate-900` |
| Texto secundario | `#475569`, `--rsu-slate-600` |
| Fondo de trabajo | `#f8fafc`, `--rsu-slate-50` |
| Bordes | `#e2e8f0`, `--rsu-slate-200` |
| Acción principal | `#15803d`, `--rsu-emerald-700` |
| Foco y verde oscuro | `#166534`, `--rsu-emerald-800` |
| Fondo de acceso institucional | `#052e16` |
| Advertencia / edición | `#f59e0b`, texto oscuro |
| Eliminación y error | `#dc2626` |

Los nombres heredados `--t-celeste` y `--t-marino` se conservan como alias: cambiar sus nombres sin actualizar consumidores rompería estilos. Cancelar es una acción neutral; el rojo se reserva para eliminación o error. Un estado debe tener texto o un nombre accesible, además del color.

## Tipografía y composición

Se utiliza Source Sans 3 alojada en `public/vendor/fonts/source-sans-3/`, con fuentes de sistema como respaldo. Títulos administrativos de 1.55rem (1.3rem en móvil), subtítulos de .88rem y reloj de Inicio de 2rem. Mantenga una sola cabecera principal y use h2 para secciones. No aplique mayúsculas a párrafos.

El espaciado de Inicio utiliza 4, 6, 10, 20, 24 y 32px según agrupación. Bordes de 1px, radios de 6–12px y sombras discretas existentes. El encabezado se separa del contenido con una línea; Inicio muestra la jornada y después herramientas, con la fotografía institucional en un papel secundario.

## Componentes y comportamiento

- `x-admin.cabecera`: título, explicación y acción principal. El partial anterior delega en este componente.
- `public/js/gestor.js`: tablas, formularios, errores y confirmación de eliminación.
- `resources/views/components/admin/`: botones, tarjetas, modales, indicadores y estados vacíos disponibles. Reutilice los que correspondan; no todos deben aparecer en cada pantalla.
- Iconos de Bootstrap Icons ya instalados. Los adornos llevan `aria-hidden`; los botones de icono necesitan nombre accesible.
- Los controles conservan foco visible. El menú usa contorno blanco; los controles claros usan verde oscuro. No quite outlines para ocultar el foco.
- La validación de login asocia los mensajes con el campo mediante `aria-describedby` y declara `aria-invalid`.

## Adaptación y accesibilidad

La jornada permite envolver sus elementos. Inicio pasa a una columna a 900px, y los accesos rápidos a una columna a 600px. Las tablas conservan contenedor con desplazamiento horizontal; no oculte columnas esenciales para hacerlas caber.

El acceso oculta su panel institucional por debajo de 900px. Respete preferencias de movimiento reducido. Evite usar un gráfico, una animación o una fotografía para comunicar un dato necesario.

WCAG 2.2 AA es el objetivo, no una certificación obtenida en esta sesión. Se revisaron visualmente Inicio y Modelos en escritorio, navegación y formulario modal. Falta comprobar todos los tamaños móviles, lectores de pantalla, zoom y contraste de todas las variantes heredadas.

## Archivos y comprobación

`public/css/tads.css` contiene estilos internos; `tads-menu.css`, navegación; `tads-login.css`, acceso. No edite assets de terceros. Los estilos del perfil Jetstream que están en `resources` requieren `npm run build`.

Comparación del inicio: [antes](docs/capturas/inicio-antes.png) y [después](docs/capturas/inicio-despues.png). Son capturas del navegador de esta revisión, con datos de prueba. No existe una captura anterior del login en esta entrega.
