# Plan original del conductor

Los pasos y resultados esperados se conservan de las referencias. Marca cada caso al ejecutarlo y registra la evidencia por entorno.

| Caso | Pasos | Resultado esperado | Registro |
| --- | --- | --- | --- |
| A1 | Tocar ⚙ y poner 10.0.2.2:8000 sin http://. Luego http://10.0.2.2:8000 | Primero avisa que debe empezar con http:// o https://. Luego muestra "Servidor: …" | ☐ |
| A2 | Tocar Ingresar sin llenar nada | "Ingrese su correo." y "Ingrese su contraseña." | ☐ |
| A3 | Correo mal escrito: carlos@ | "Ingrese un correo válido." | ☐ |
| A4 | Contraseña incorrecta | Recuadro rojo con el mensaje del servidor, no entra | ☐ |
| A5 | Entrar con una cuenta que no es conductor (por ejemplo el admin) | No debe entrar | ☐ |
| A6 | Tocar el ojo de la contraseña | Muestra y oculta la clave | ☐ |
| A7 | Detener el backend e intentar entrar | "No se pudo conectar con el servidor…" | ☐ |
| A8 | Login correcto | Abre "Mis Programaciones" con nombre y DNI del conductor | ☐ |
| A9 | Cerrar la app por completo y volver a abrirla | Entra directo, sin pedir login | ☐ |
| A10 | Ver la lista | La de hoy (insignia "Hoy") y la futura, ordenadas por fecha, con zona, vehículo y turno | ☐ |
| A11 | Deslizar hacia abajo | Recarga la lista | ☐ |
| A12 | Tocar la programación futura | Detalle con "Programación pendiente" y el botón de iniciar desactivado | ☐ |
| A13 | Tocar Mi perfil (icono de persona) | DNI, licencia, correo y teléfono correctos | ☐ |
| A14 | Abrir el menú lateral | Mis programaciones, Programación de hoy e Historial abren su pantalla | ☐ |
| A15 | Cerrar sesión (desde el menú y desde Mi perfil) | Vuelve al login; al reabrir la app pide login | ☐ |
| A16 | Entrar con el conductor 2 | "No tiene programaciones pendientes." | ☐ |
| A17 | Conductor 2 → Programación de hoy | "No hay programación para hoy" | ☐ |
| A18 | Conductor 1 → Programación de hoy | Fecha, zona, vehículo con placa, ayudantes y turno coinciden con el sistema web | ☐ |
| A19 | Con un ayudante sin asistencia | Ese ayudante sale "Ausente" en rojo; INICIAR RECORRIDO desactivado, con el motivo en ámbar | ☐ |
| A20 | Completar la asistencia en el sistema web y deslizar para recargar | Todos "Presente" y el botón se activa | ☐ |
| A21 | Programación de un turno que empieza en más de 30 minutos | Botón desactivado | ☐ |
| A22 | Tocar INICIAR RECORRIDO y luego Cancelar | Aparece "¡Todo el personal ha asistido!"; con Cancelar no pasa nada | ☐ |
| A23 | Negar el permiso de ubicación | "Debe permitir el acceso a la ubicación…" y no inicia | ☐ |
| A24 | Con el GPS apagado (celular real) | "Active la ubicación (GPS) del teléfono…" | ☐ |
| B1 | Iniciar el recorrido | Abre "Recorrido en curso" con zona, vehículo, ayudantes, insignia "En ruta" y el reloj avanzando | ☐ |
| B2 | Esperar el primer punto | Pasa de "Buscando señal GPS…" a "Registrando ubicación cada 5 segundos…" con coordenadas | ☐ |
| B3 | Moverse (o Play route en el emulador) | La línea del recorrido se dibuja y crece en el mapa | ☐ |
| B4 | Ir atrás, a Programación de hoy | Dice "Recorrido en curso"; VER RECORRIDO EN CURSO vuelve al mapa sin perder la línea | ☐ |
| B5 | Ver Mis programaciones | La de hoy muestra la insignia "En ruta" | ☐ |
| B6 | Bloquear la pantalla 1 a 2 minutos caminando (celular real) | Notificación "Recorrido en curso"; al desbloquear, la línea cubre ese tramo | ☐ |
| B7 | Modo avión (o detener el backend) 1 minuto, luego reconectar | "Sin conexión: N ubicaciones por enviar"; al volver baja a 0 y el tramo aparece | ☐ |
| B8 | Revisar en el sistema web como admin | El recorrido aparece en curso con sus ubicaciones | ☐ |
| B9 | Abrir Finalizar | Muestra zona, vehículo, ayudantes, turno y fecha y hora de inicio | ☐ |
| B10 | Finalizar con la observación vacía | "La observación es obligatoria." | ☐ |
| B11 | Observación de 1 o 2 letras | "Escriba al menos 3 caracteres." | ☐ |
| B12 | Pegar un texto muy largo | El contador llega a 500/500 y no deja escribir más | ☐ |
| B13 | Tocar Finalizar y luego Cancelar | Aparece "¿Finalizar recorrido?"; con Cancelar el recorrido sigue | ☐ |
| B14 | Finalizar y confirmar | "Recorrido finalizado" con la hora; se apaga la notificación del GPS y abre el detalle | ☐ |
| B15 | Volver a Programación de hoy | Botón gris "Recorrido completado a las HH:MM"; no se puede iniciar otro | ☐ |
| B16 | Historial con el conductor sin recorridos | "Todavía no tiene recorridos registrados." | ☐ |
| B17 | Historial con el conductor que finalizó | El recorrido aparece con fecha, horario, zona, vehículo y estado | ☐ |
| B18 | Abrir el detalle | Mapa con inicio (verde) y fin (rojo), horas, ayudantes y la observación de B14 | ☐ |
| B19 | Tocar "Ver recorrido en mapa" | Mapa grande con el número de ubicaciones registradas | ☐ |
| B20 | Iniciar y finalizar enseguida, sin GPS | "Este recorrido no tiene ubicaciones registradas." | ☐ |
| B21 | Comparar con el sistema web | Horas, observación y trayectoria coinciden con lo que ve el admin | ☐ |
