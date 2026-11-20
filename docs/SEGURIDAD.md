# Seguridad

El perfil V4.1 y los controles ensayados se detallan en [seguridad del despliegue](despliegue/SEGURIDAD_Y_PRIVACIDAD.md).

Laravel valida las mutaciones en el servidor. El panel usa sesiones, CSRF y permisos. La API de conductor usa un token personal de Sanctum con habilidad específica y verifica el estado y propiedad en servidor. Los tokens emitidos desde este cambio vencen a las 24 horas. Para instalaciones que ya emitieron tokens sin vencimiento, planificar y comunicar su revocación durante el piloto. Los binarios exigen HTTPS, con una excepción de laboratorio compilada exclusivamente para loopback Android. La sesión se guarda en SecureStore.

La consulta vecinal devuelve únicamente nombre y distrito de la zona, identificador, horario y estado del turno. Mantener el laboratorio restringido. Antes de exponer la proyección a Internet se requiere aprobar la política de publicación de horarios y habilitar un frontal que sirva solo las rutas públicas previstas. El panel, almacenamiento privado y base de datos permanecen en la red de operación.

Revisar los resultados y requisitos pendientes descritos en [pruebas](PRUEBAS.md) antes de un piloto con datos reales. Usar contraseñas únicas, `APP_DEBUG=false`, directorio público como raíz web y copias de seguridad protegidas.

Composer quedó sin avisos tras actualizar Laravel y Symfony. El registro de npm notificó 13 entradas en la cadena de dependencias móvil, incluidas dos altas en paquetes de herramientas de compilación (procesamiento de imágenes y PostCSS). El laboratorio compila recursos del repositorio. Queda por resolver la actualización del árbol de Expo y repetir las pruebas antes de distribuir fuera del laboratorio.

La auditoría web completa inicialmente encontró tres entradas. Se corrigió el aviso alto mediante actualizaciones compatibles con el lockfile. Quedan dos entradas moderadas en el procesamiento de selectores CSS de Tailwind. El build Vite se repitió con el lockfile actualizado.
