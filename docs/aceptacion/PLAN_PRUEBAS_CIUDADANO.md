# Plan ciudadano V3

Los pasos y resultados esperados se conservan de las referencias. Marca cada caso al ejecutarlo y registra la evidencia por entorno.

| Caso | Pasos | Resultado esperado | Registro |
| --- | --- | --- | --- |
| C1 | Abrir configuración de servidor y escribir URL sin esquema; luego origen autorizado. | Mensaje de formato, prueba de conexión y origen guardado; HTTPS obligatorio en distribución. | ☐ |
| C2 | Abrir registro y enviar todos los campos vacíos. | Errores visibles de DNI, nombres, apellidos, correo, contraseña y zona; sin alta parcial. | ☐ |
| C3 | Escribir DNI de 7/9 dígitos, letras, luego 8 dígitos incluso con cero inicial. | Rechaza inválidos y conserva DNI válido como texto. | ☐ |
| C4 | Registrar con correo inválido y contraseña inferior a la política documentada. | Mensajes comprensibles; servidor rechaza datos inválidos. | ☐ |
| C5 | Registrar con DNI ya registrado. | Rechazo conforme a política de unicidad, sin duplicado ni fuga de datos ajenos. | ☐ |
| C6 | Registrar con correo duplicado. | Sin segunda identidad incompatible; mensaje controlado acorde a política. | ☐ |
| C7 | Intentar registrar sin zona o enviar zona inexistente/inactiva por API. | Rechazo servidor y app; no se guarda el ciudadano inválido. | ☐ |
| C8 | Registrar ciudadano válido de zona Norte. | Cuenta persistida, datos coinciden con servidor, contraseña almacenada como hash; sesión/login funcional. | ☐ |
| C9 | Probar login con campos vacíos y correo incorrecto. | Validaciones de campos; no inicia sesión. | ☐ |
| C10 | Probar contraseña incorrecta y cuenta conductor/admin. | No entra a la app ciudadana como ciudadano; no se elevan privilegios. | ☐ |
| C11 | Login válido y mostrar/ocultar contraseña. | Navega a Mis programaciones; control de visibilidad funcional. | ☐ |
| C12 | Cerrar app por completo y reabrir con sesión vigente. | Recupera cuenta y zona sin pedir login innecesario. | ☐ |
| C13 | Abrir perfil y cerrar sesión; reabrir app. | Perfil propio correcto, token invalidado según política y caché privada limpiada; pide login. | ☐ |
| C14 | Detener servidor e intentar iniciar sesión. | Aviso de conexión; no fabrica login exitoso. | ☐ |
| C15 | Solicitar Encontrar mi ubicación con permiso concedido y coordenada interior de zona Norte. | Mapa marca posición, identifica polígono y zona correctos; requiere confirmación. | ☐ |
| C16 | Denegar permiso de ubicación. | Explica permiso y permite selección manual sin bloquear registro/consulta. | ☐ |
| C17 | Desactivar GPS. | Indica que debe activarse y ofrece selección manual; sin coordenadas inventadas. | ☐ |
| C18 | Ubicar punto fuera de los polígonos activos. | Informa zona no encontrada y permite elección manual válida. | ☐ |
| C19 | Probar punto en borde o cobertura ambigua. | Aplica política documentada; muestra opciones si procede, no asigna zona incorrecta silenciosamente. | ☐ |
| C20 | Seleccionar zona manualmente y confirmarla; reabrir app. | Guarda zona en backend/perfil y carga programaciones correspondientes. | ☐ |
| C21 | Ver lista con programación de hoy y futura. | Fecha, turno y estado reales; agrupación/orden correcto. | ☐ |
| C22 | Cambiar filtro mensual. | Solo programaciones del período seleccionado para zona autorizada, con criterio documentado para turno nocturno. | ☐ |
| C23 | Deslizar para recargar después de reprogramación administrativa. | Lista refleja cambio real sin duplicados ni datos viejos presentados como actuales. | ☐ |
| C24 | Entrar con ciudadano de una zona sin programaciones. | Estado vacío entendible, no error ni datos ficticios. | ☐ |
| C25 | Abrir detalle de programación válida. | Zona, vehículo, placa, conductor, ayudantes, turno, fecha, estado y polígono coinciden con datos de prueba web. | ☐ |
| C26 | Intentar consultar detalle de zona ajena alterando ID; cambiar zona por flujo autorizado. | API rechaza acceso fuera de alcance y permite solo la zona autorizada tras cambio; no filtra datos privados. | ☐ |
| D1 | Abrir seguimiento antes de que conductor inicie. | Muestra Programado/sin recorrido; sin marcador de camión inventado ni valoración habilitada. | ☐ |
| D2 | Conductor autorizado inicia recorrido mientras ciudadano sigue pantalla. | Estado En curso aparece y reloj/metadata coherentes; medir latencia. | ☐ |
| D3 | Conductor registra sucesivas lecturas nominalmente cada 5 s, con ruta física o simulada identificada. | Marcador del camión se actualiza, trayectoria crece y coincide con puntos persistidos; medir intervalos y latencia. | ☐ |
| D4 | Conceder ubicación al ciudadano. | Su marcador es distinto del camión y del recorrido; leyenda correcta. | ☐ |
| D5 | Denegar ubicación durante el seguimiento. | El seguimiento del camión sigue disponible; no se dibuja ubicación propia falsa. | ☐ |
| D6 | Interrumpir Internet del ciudadano durante 1 min. | Muestra datos en caché, antigüedad y aviso sin conexión; no dice tiempo real mientras está desconectado. | ☐ |
| D7 | Restaurar Internet del ciudadano. | Recupera puntos faltantes y estado actualizado sin duplicar trayectoria. | ☐ |
| D8 | Conductor pierde red durante 1 min y luego sincroniza. | Ciudadano señala última actualización antigua y recupera tramo confirmado al volver el conductor. | ☐ |
| D9 | Salir de pantalla y volver; cerrar/reabrir app con recorrido activo. | Recupera estado y trayectoria desde servidor, con caché si corresponde. | ☐ |
| D10 | Intentar valorar recorrido aún no finalizado, inclusive por API. | Valoración rechazada; no se crea fila ni aumenta promedio/conteo. | ☐ |
| D11 | Conductor finaliza con observación válida. | Ciudadano muestra Finalizado/Completado y habilita calificación; se conserva la trayectoria. | ☐ |
| D12 | Entrar a Calificar servicio sin elegir estrellas; enviar 0/6 o decimal por API. | Mensaje de selección obligatoria y rechazo de valor fuera de 1..5 o no entero. | ☐ |
| D13 | Asignar 4 estrellas y comentario opcional válido. | Registra una valoración ligada al ciudadano y recorrido correctos; éxito tras confirmación. | ☐ |
| D14 | Asignar 5 estrellas sin comentario con otro ciudadano elegible. | Registro válido con comentario vacío y promedio/conteo recalculados (4.5 y 2 para fixture limpio). | ☐ |
| D15 | Pegar comentario superior al límite definido e intentar HTML/script. | Valida límite; muestra contenido como texto seguro o rechaza según contrato; sin ejecución ni desbordamiento. | ☐ |
| D16 | Pulsar Enviar dos veces, reintentar tras respuesta perdida y manipular identificador de usuario. | Una sola valoración contabilizada por ciudadano/recorrido; identidad derivada del token. | ☐ |
| D17 | Intentar valorar recorrido de otra zona sin elegibilidad. | Servidor rechaza; sin cambios en promedio ni conteo. | ☐ |
| D18 | Ver historial con ciudadano sin recorridos autorizados finalizados. | Mensaje vacío, no valoraciones ficticias. | ☐ |
| D19 | Ver historial con recorridos finalizados y valoraciones. | Fecha, zona, turno, estado, promedio/conteo coherentes; distingue sin valoraciones. | ☐ |
| D20 | Abrir detalle histórico de recorrido valorado. | Trayectoria persistida, marcadores inicio/fin, datos de servicio permitidos, promedio y valoración/comentario propio visibles según contrato. | ☐ |
| D21 | Abrir detalle de recorrido sin ubicaciones registradas. | Mensaje sin ubicaciones; sin línea ni puntos sintéticos. | ☐ |
| D22 | Comparar app, panel administrativo, API y BD tras reabrir sesión. | Horas, estados, ruta, conteo y valoración coinciden según permisos; otro usuario no ve caché o comentario privado ajeno. | ☐ |
