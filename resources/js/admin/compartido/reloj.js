(function (global) {
    'use strict';
    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};
    /* ------------------------------------------------------------------ Reloj del servidor */

    /**
     * Reloj que muestra la hora del SERVIDOR (la que se guarda en las marcaciones), no la de la PC.
     * Consulta { servidor, zona, turno, ... } cada cierto tiempo, mide el desfase y avanza solo cada segundo.
     *   Tads.relojDelServidor({ url, hora, fecha, turno, formatoFecha, alEstado(json) })
     * hora/fecha/turno son elementos (opcionales) donde se pinta; alEstado recibe la respuesta completa.
     */
    Tads.relojDelServidor = function ({ url, hora, fecha, turno, formatoFecha = 'larga', cadaMs = 30000, alEstado }) {
        let desfase = 0;
        let fmtHora = null;
        let fmtFecha = null;

        function tic() {
            if (!fmtHora) return;
            const ahora = new Date(Date.now() + desfase);
            if (hora) hora.textContent = fmtHora.format(ahora);
            if (fecha) {
                const texto = fmtFecha.format(ahora);
                fecha.textContent = texto.charAt(0).toUpperCase() + texto.slice(1);
            }
        }

        async function consultar() {
            const enviado = Date.now();
            const { ok, json } = await Tads.peticion(url);
            if (!ok || !json) return;

            // Se descuenta la mitad del viaje de ida y vuelta para no adelantar ni atrasar el reloj.
            desfase = new Date(json.servidor).getTime() + (Date.now() - enviado) / 2 - Date.now();

            if (!fmtHora) {
                fmtHora = new Intl.DateTimeFormat('es-PE', { timeZone: json.zona, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
                fmtFecha = new Intl.DateTimeFormat('es-PE', formatoFecha === 'corta'
                    ? { timeZone: json.zona, weekday: 'long', day: 'numeric', month: 'long' }
                    : { timeZone: json.zona, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            }
            tic();

            if (turno) {
                turno.innerHTML = json.turno
                    ? `<i class="bi bi-clock me-1"></i> Turno ${Tads.escapar(json.turno)}`
                    : '<i class="bi bi-moon-stars me-1"></i> Fuera de turno';
            }
            alEstado?.(json);
        }

        consultar();
        setInterval(tic, 1000);
        setInterval(consultar, cadaMs);

        return { consultar };
    };


})(window);
