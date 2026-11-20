# Laboratorio RSU V4.2

La prueba usó el mismo backend para las dos aplicaciones y el panel. Desde el anfitrión Windows, el origen verificado fue `http://127.0.0.1:8111`. Desde Android emulado, el origen fue `http://10.0.2.2:8111`. Los APK conservan el selector de origen de laboratorio. El puerto 8104 corresponde al entorno anterior.

Para levantar una copia, usa el [procedimiento de instalación](INSTALACION_LIMPIA.md) con el código V4.2, un esquema MySQL de demostración y las credenciales de laboratorio propias. Verifica primero `/api/v1/salud` y el acceso al panel. En los dos APK, selecciona el mismo origen mediante **Configurar servidor**. Prueba un recorrido con coordenadas de ensayo.

Los APK del manifiesto provienen de `817086b` y llevan firma de laboratorio. Mantienen los identificadores `pe.rsu.conductor` y `pe.rsu.cliente`. Usa la [guía de contingencia](GUIA_EMERGENCIA_EXPOSICION.md) para una red privada autorizada. Los ensayos en dispositivos físicos y los perfiles externos constan en [intervenciones](INTERVENCIONES_V4_2.md).
