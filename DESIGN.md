# Diseño del sistema RSU

## Dirección actual · 23 de octubre de 2026

Interfaz municipal con componentes de precisión, inspirados en el acabado de shadcn/ui y adaptados a Blade, Bootstrap y DataTables. La identidad procede de la fotografía del palacio municipal, la marca oficial y un verde vegetal contenido. La revisión sustituye la dirección demasiado austera del primer pase.

El acceso contrapone una fotografía del lugar y un formulario delineado; el panel utiliza superficies claras, navegación lateral suave, tablas limpias y modales blancos. No hay una migración a React ni componentes shadcn instalados: se conserva la interacción nativa de las herramientas existentes.

## Paleta semántica

| Uso | Color / token |
|---|---|
| Texto principal | `#202a25`, `--rsu-slate-900` |
| Texto secundario | `#5b6a61`, `--rsu-slate-600` |
| Texto atenuado / placeholders | `#68766e`, `--rsu-slate-500` |
| Fondo lateral | `#f8faf7` |
| Fondo de trabajo | `#fbfcfa` |
| Superficies | Blanco |
| Bordes | `#e5e9e5`, `--rsu-slate-200` |
| Acción principal | `#245d46`, `--rsu-emerald-700` |
| Selección del menú | `#e7efe8`, texto `#234e38` |
| Acción destructiva | `#b44141` |
| Foto: superposición de contraste | `#132b24` con opacidad progresiva |

Los tokens heredados conservan sus nombres por compatibilidad. En esta versión `slate` identifica una gama neutral con matiz vegetal. No use el color como única señal de un estado. Los indicadores de negocio conservan sus variantes semánticas.

## Componentes

- **Campos:** superficie blanca, borde de 1px, radio de 7px, sombra de 1px muy suave y foco verde translúcido. Etiquetas en caja normal. Login: 44px de alto; formularios del panel: mínimo 38px y texto de 16px en móvil para evitar zoom automático en iOS.
- **Botones:** primario verde, secundario delineado, acciones de fila discretas. No se elevan ni saltan al pasar el cursor. El botón de eliminación de fila usa icono rojo y fondo suave solo al interactuar. `danger` y `cancel` son variantes diferentes en `x-admin.boton`.
- **Tablas:** encabezados sin mayúsculas forzadas, líneas horizontales finas, filas blancas y hover neutral. La página actual tiene fondo gris y borde suave. El contenedor de desplazamiento conserva todas las columnas.
- **Modales:** cabecera blanca, título de 1.12rem, borde fino, radio de 12px, cierre discreto y sombra reservada para la separación entre capas. Bootstrap conserva el control de foco y el cierre.
- **Navegación:** tema claro nativo de AdminLTE; selección verde suave, textos oscuros y separación de 1px. Cabecera blanca. Se desactiva el preloader de pantalla completa: no oculta una página HTML ya disponible.
- **Confirmaciones:** SweetAlert utiliza tipografía heredada, acciones coherentes y radios de 7–12px.
- **Buscadores:** mismo campo delineado; sugerencias sobre una superficie blanca con borde y sombra de popover.

## Tipografía y ritmo

Fuente del sistema: Segoe UI con respaldo `system-ui`. Títulos administrativos de 1.65rem, peso 650 y espaciado -0.035em. Cuerpo de datos de .82rem; etiquetas de .8rem. Fotografía de acceso con titular de 36–58px y un texto breve. Las cifras de datos conservan números tabulares.

Los radios de controles son de 7px, tarjetas de 10px y paneles de acceso de 16px. Use espacios de 8, 12, 20, 24 y 40px para separar controles, grupos y superficies. Las sombras se reservan para controles leves y capas superpuestas, sin brillo de color.

## Acceso y adaptación

`x-acceso` es compartido por login, confirmación, recuperación y 2FA. Conserva el formulario dentro del slot y todos sus atributos y endpoints. La fotografía es `public/images/municipalidad.jpg`, ya existente; no es una imagen generada ni un recurso descargado.

A 760px el panel fotográfico se retira para que el formulario ocupe el espacio disponible. La interfaz se comprobó visualmente a 1280×720 y el acceso y modal a 390×844. Inicio conserva su adaptación a una columna a 900px; los accesos pasan a una columna a 600px.

## Accesibilidad y estados

Se conservan etiquetas, CSRF, autocompletado, visibilidad de contraseña, bloqueo de doble envío y errores ARIA de login. No se elimina el foco de teclado. El verde principal sobre blanco tiene contraste aproximado 7.69:1; el placeholder gris sobre blanco, 4.77:1. Estas comprobaciones de colores concretos no son una certificación WCAG de todo el producto.

El movimiento reducido desactiva transiciones en los controles y modales. La navegación mantiene nombres y rutas; no se añade un flujo de navegación nuevo. Quedan pendientes las comprobaciones exhaustivas con lectores de pantalla y todos los módulos/estados particulares.

## Archivos y verificación

- `public/css/tads-login.css`: acceso y sus estados.
- `public/css/tads-menu.css`: lateral y cabecera.
- `public/css/tads.css`: componentes administrativos.
- `config/adminlte.php`: tema claro y preloader desactivado.
- `resources/views/components/acceso.blade.php`: composición del acceso.
- `resources/views/components/admin/boton.blade.php`: variantes de botón.

Capturas actuales: [acceso](docs/capturas/acceso-pulido.png), [acceso móvil](docs/capturas/acceso-movil.png), [tabla](docs/capturas/modelos-pulido.png), [modal](docs/capturas/formulario-pulido.png) y [modal móvil](docs/capturas/formulario-movil.png). Las capturas `inicio-antes.png` e `inicio-despues.png` documentan el primer pase, no la paleta actual.

Referencias de componentes: [Input](https://ui.shadcn.com/docs/components/input), [Button](https://ui.shadcn.com/docs/components/button) y [Dialog](https://ui.shadcn.com/docs/components/dialog), documentación oficial de shadcn/ui. Se usa como referencia visual; la implementación continúa en los componentes de este repositorio.
