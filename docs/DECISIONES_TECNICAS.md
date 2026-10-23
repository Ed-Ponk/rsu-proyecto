# Decisiones técnicas

## Laravel y responsabilidades

Se conservan controladores Admin, modelos Eloquent y servicios con reglas reales. Las entradas complejas se separan en `app/Http/Requests/Admin`; `datos()` valida explícitamente después de adquirir bloqueos. Catálogos sencillos conservan validadores locales y la comprobación en vivo comparte reglas con guardar. No hay repositorios ni interfaces añadidas por anticipación.

## Escrituras protegidas

`EscrituraDelPanel` mantiene validación y escritura en una transacción, bloquea conjuntos estables antes de persona/registro y compara versiones. La lectura inicial de identificación ocurre antes de la transacción para no fijar una vista antigua de datos en MySQL REPEATABLE READ mientras se espera un bloqueo. La autorización se vuelve a comprobar bajo bloqueo del actor. El servicio DNI y el cierre programado conservan protección también fuera de HTTP.

## Compatibilidad

Se mantienen rutas y claves de negocio; se añade `version` a ediciones y acciones. Estado de contrato recibe el estado deseado. JSON de tablas tiene rutas `/datos`; los listados anteriores conservan negociación por compatibilidad. Las migraciones históricas no se reescriben y los datos incompatibles se informan mediante diagnóstico sin corregirlos automáticamente.

## Fuentes de interfaz

El panel, el menú y el acceso se compilan con Vite desde `resources`. El layout compartido carga JS después de las librerías de AdminLTE; se conserva `Tads` y se separan módulos y piezas comunes. Terceros permanecen publicados en `public/vendor`. Nunca editar `public/build` ni mantener una segunda copia propia en `public/js`.

## Reglas de negocio conservadas

Vacaciones usan días corridos y cupo del año inicial; aprobación iniciada requiere periodo no terminado y sin asistencia. Placas no se fusionan por separadores. La geometría usa cajas aproximadas y tolerancia de 1%, con límite de 200 vértices y precisión almacenada de siete decimales. No se añaden roles ni planificación de rutas.

## Pruebas y operación

SQLite verifica HTTP y reglas; MySQL verifica integridad y dos procesos simultáneos con base descartable. No confundir ambos resultados. Ver `IMPLEMENTACION_Y_VERIFICACION.md` para contratos, despliegue y límites, y `TRES_RECORRIDOS_DEL_CODIGO.md` para empezar a modificar módulos.
