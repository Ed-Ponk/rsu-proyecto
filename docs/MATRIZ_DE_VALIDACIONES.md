# Matriz de validaciones

| Entrada o acción | Control servidor | Resultado |
| --- | --- | --- |
| Campos de personal y usuario | Formatos DNI, edad, email, teléfono y licencia, unicidad y rol permitido | 422 con errores de campo |
| Archivos de personal y vehículo | Validación de imagen, formato y tamaño en controladores | Rechazo de archivo fuera de política |
| Catálogo y referencias | Unicidad, claves foráneas y restricciones de borrado | Error de campo o de integridad |
| Fechas, contrato, asistencia y vacaciones | Servicios RN-05–08 y restricciones de BD | Rechazo ante conflicto |
| Recursos y transiciones de programación | RN-09 y transacciones de actualización | La operación se cancela ante recursos o estado incompatibles |
| Credencial móvil | Campos acotados, verificación de hash, rol activo, limitación por origen | 422 o 429 |
| Token y propiedad | Sanctum, token personal y habilidad, propietario en query | 401, 403 o 404 |
| Inicio y cierre | Asistencia, ventana, estado y longitud de observación | 422; la aplicación solicita confirmación |
| GPS | Latitud ±90, longitud ±180, precisión no negativa, fecha y lote acotados | 422 para formato inválido, conteo para puntos fuera de ventana |
| Consulta ciudadana | ID entero positivo, zona activa, cuota por origen | 422, 404 o 429 |

Pendientes para piloto ampliado: concurrencia en el motor de producción, sincronización durable de GPS y verificación de recuperación tras interrupción con dispositivos reales. Consultar [pruebas](PRUEBAS.md).
