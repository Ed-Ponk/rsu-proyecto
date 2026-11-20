# Regresión del servidor

- SQLite. 187 pruebas, 181 aprobadas, 6 omitidas, 1 895 aserciones y Pint aprobado. Evidencia JUnit en `phpunit-sqlite.xml`.
- MySQL. API sobre esquema aislado, dos ejecuciones consecutivas de 19 pruebas y 183 aserciones. La regresión completa de todas las interfaces MySQL sigue pendiente.
- Web. Vite compiló los recursos desde un checkout limpio y desde el workspace activo. Se comprobaron 42 rutas del panel.
- Android. Gradle generó dos APK arm64-v8a y x86_64. Se inspeccionaron firma, permisos, versión e instalación sobre Android 36 emulado.

La [matriz de casos](RESULTADOS_V4_1.csv) separa estas comprobaciones de la aceptación académica.
