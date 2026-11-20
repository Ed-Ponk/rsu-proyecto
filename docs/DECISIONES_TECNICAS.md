# ADR 001 · distribución móvil híbrida

| Alternativa | Distribución y UX | Coste, cambios y seguridad |
| --- | --- | --- |
| Dos proyectos independientes | Marca, permisos y publicación propios | Duplica cliente REST, componentes y mantenimiento; facilita ciclos de publicación aislados |
| Una aplicación multirrol | Una instalación y navegación por actor | Requiere mantener caminos públicos y operativos en un mismo producto y solicitar permisos según contexto |
| Dos variantes con código compartido | Dos identidades y accesos directos a cada experiencia | Un proyecto, configuración por compilación, controles de rol en Laravel |

Se eligió la tercera. Expo con React Native reutiliza JavaScript y UI en Android e iOS, aporta almacenamiento seguro y permisos de ubicación, y admite un bundle local con Metro. Flutter también ofrecería una base común con nueva cadena de herramientas. Kotlin nativo entrega integración Android más directa y exigiría otro cliente para iOS. La distribución híbrida mantiene la consulta ciudadana sin pedir geolocalización e identifica claramente la app operativa.

Se reutiliza el descendiente oficial `avance-06-api` por sus migraciones acumulativas y lógica de programación existente. La sincronización ciudadana usa lectura REST cada 30 s mientras hay zona seleccionada y actualización manual. El piloto usa GPS en primer plano a demanda y recupera el recorrido activo desde el servidor.
