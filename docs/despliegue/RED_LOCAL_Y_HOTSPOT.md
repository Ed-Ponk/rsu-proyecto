# Red local y hotspot

Usa la [guía de contingencia](GUIA_EMERGENCIA_EXPOSICION.md) con una red propia autorizada. Registra adaptador, subred, puerto, origen, dispositivo y hora antes de la prueba. El anfitrión y dos clientes deben conectarse al mismo Laravel, aunque se retire la salida a Internet.

Publica únicamente el frontal, en la IP privada del adaptador. Si se necesita una regla de firewall, crea una regla dedicada para ese puerto, perfil privado, interfaz y subred. Retira esa regla al finalizar. Mantén los puertos de MySQL y phpMyAdmin inaccesibles desde otros equipos.

Cuando cambie la red, verifica primero comunicación directa entre clientes, luego salud HTTP, autenticación y un recorrido completo. La interfaz activa durante la intervención tenía perfil público y no se cambió su clasificación. Los ensayos físicos DEP04–DEP08 necesitan dos clientes autorizados y una red de prueba.
