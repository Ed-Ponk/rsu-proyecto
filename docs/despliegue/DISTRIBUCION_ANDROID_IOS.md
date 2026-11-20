# Distribución móvil

Los APK Android disponibles para V4.1 son los candidatos de laboratorio 1.2.0, código de versión 3, compilados del código móvil en `974c6e9`. Identificadores `pe.rsu.conductor` y `pe.rsu.cliente`. El manifiesto de entrega contiene SHA-256, ABI, firma y permisos. Los ensayos B2 y B7 reutilizaron el APK V3 del conductor contra el servidor actualizado. El APK V4.1 de Ciudadano conserva solo permisos de ubicación en primer plano.

Para instalar desde un archivo conservado verifica primero el hash y la firma. Habilita la instalación desde el gestor de archivos elegido solo para ese origen y retira el permiso al terminar. Configura la misma dirección del servidor de ensayo en las dos aplicaciones. El perfil por distribuir requiere un origen HTTPS controlado y un ensayo de instalación y actualización en teléfonos físicos.

La distribución nativa de iOS queda pendiente de compilación y firma autorizadas. Una PWA para consulta ciudadana requiere HTTPS y validación posterior en Safari. La captura GPS nativa del conductor requiere pruebas propias.
