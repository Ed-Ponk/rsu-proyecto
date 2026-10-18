/**
 * Módulo de Marcación con DNI (Tads)
 *
 * Quiosco donde el personal registra su entrada o salida con su DNI y su clave:
 *   - reloj sincronizado con la hora del SERVIDOR (es la que se guarda), no con la de la PC del quiosco,
 *   - turno vigente y últimas marcaciones, consultados periódicamente al backend,
 *   - envío asíncrono del formulario y tarjeta de confirmación (entrada/salida) que se oculta sola.
 * Toda regla (credenciales, intentos, espera, contrato vigente) se valida en el controlador.
 */
(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    const CADA_ESTADO_MS = 30000;      // refresco de turno y últimas marcaciones
    const OCULTAR_RESULTADO_MS = 9000; // la confirmación se borra para el siguiente trabajador

    Tads.Modulos.marcacion = {
        init: function (contenedor) {
            const urlEstado = contenedor.dataset.urlEstado;
            const urlMarcar = contenedor.dataset.urlMarcar;

            const reloj = document.getElementById('reloj');
            const relojFecha = document.getElementById('relojFecha');
            const relojTurno = document.getElementById('relojTurno');
            const ultimas = document.getElementById('ultimasMarcaciones');
            const formulario = document.getElementById('formMarcacion');
            const dni = document.getElementById('marcaDni');
            const clave = document.getElementById('marcaClave');
            const boton = document.getElementById('botonMarcar');
            const resultado = document.getElementById('resultadoMarcacion');

            /* ---------- Estado: turno y últimas marcaciones (los decide el backend) ---------- */
            function pintarUltimas(lista) {
                if (!lista.length) {
                    ultimas.innerHTML = '<li class="text-muted">Aún no hay marcaciones hoy.</li>';
                    return;
                }
                ultimas.innerHTML = lista.map((m) => `
                    <li>
                        ${m.tipo === 'entrada' ? Tads.insignia('Entrada', 'verde') : Tads.insignia('Salida', 'ambar')}
                        <span class="t-ultimas-persona">${Tads.escapar(m.persona)}<small>${Tads.escapar(m.funcion ?? '')}${m.origen === 'marcacion' ? ' · DNI' : ' · Manual'}</small></span>
                        ${m.tipo === 'entrada' && m.tardanza > 0 ? Tads.insignia(`${m.tardanza} min tarde`, 'rojo') : ''}
                        <span class="t-hora">${Tads.escapar(m.hora)}</span>
                    </li>`).join('');
            }

            // Reloj con la hora del servidor (componente compartido con la pantalla de inicio).
            const relojServidor = Tads.relojDelServidor({
                url: urlEstado,
                hora: reloj,
                fecha: relojFecha,
                turno: relojTurno,
                cadaMs: CADA_ESTADO_MS,
                alEstado: (json) => pintarUltimas(json.ultimas ?? []),
            });
            const cargarEstado = () => relojServidor.consultar();

            /* ---------- Confirmación ---------- */
            let temporizador = null;

            function mostrarResultado(m) {
                const esEntrada = m.tipo === 'entrada';
                const avatar = m.foto
                    ? `<img class="t-avatar t-avatar-lg" src="${Tads.escapar(m.foto)}" alt="">`
                    : `<span class="t-avatar t-avatar-lg">${Tads.escapar(m.iniciales)}</span>`;

                resultado.className = `t-resultado visible ${esEntrada ? 'entrada' : 'salida'}`;
                resultado.innerHTML = `
                    ${avatar}
                    <div class="t-resultado-datos">
                        <strong>${Tads.escapar(m.persona)}</strong>
                        <small>DNI ${Tads.escapar(m.dni)} · ${Tads.escapar(m.funcion ?? '')}</small>
                        <small>${Tads.escapar(m.fecha)} · ${Tads.escapar(m.hora)}${m.turno ? ` · Turno ${Tads.escapar(m.turno)}` : ''}</small>
                        ${esEntrada ? `<span class="mt-1">${Tads.insigniaTardanza(m.tardanza)}</span>` : ''}
                    </div>
                    <span class="t-resultado-tipo">
                        <i class="bi ${esEntrada ? 'bi-box-arrow-in-right' : 'bi-box-arrow-right'}"></i>
                        ${esEntrada ? 'Entrada' : 'Salida'}
                    </span>`;

                clearTimeout(temporizador);
                temporizador = setTimeout(() => { resultado.className = 't-resultado'; }, OCULTAR_RESULTADO_MS);
            }

            /* ---------- Formulario ---------- */
            dni.addEventListener('input', () => { dni.value = dni.value.replace(/\D/g, '').slice(0, 8); });

            document.getElementById('verClave').addEventListener('click', (e) => {
                const mostrar = clave.type === 'password';
                clave.type = mostrar ? 'text' : 'password';
                e.currentTarget.innerHTML = `<i class="bi ${mostrar ? 'bi-eye-slash' : 'bi-eye'}"></i>`;
            });

            formulario.addEventListener('submit', async (evento) => {
                evento.preventDefault();
                Tads.limpiarErrores(formulario);

                // Ayuda inmediata en el navegador; el controlador vuelve a validar todo.
                if (!/^\d{8}$/.test(dni.value)) {
                    return Tads.mostrarErrores(formulario, { errors: { dni: ['El DNI debe tener exactamente 8 dígitos.'] } });
                }
                if (!clave.value) {
                    return Tads.mostrarErrores(formulario, { errors: { password: ['Ingrese su clave de marcación.'] } });
                }

                const textoBoton = boton.innerHTML;
                boton.disabled = true;
                boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Registrando...';

                const { ok, estado, json } = await Tads.peticion(urlMarcar, { metodo: 'POST', cuerpo: new FormData(formulario) });

                boton.disabled = false;
                boton.innerHTML = textoBoton;
                clave.value = '';

                if (ok) {
                    mostrarResultado(json.marcacion);
                    Tads.aviso(json.mensaje, json.marcacion.tardanza > 0 ? 'warning' : 'success');
                    formulario.reset();
                    dni.focus();
                    return cargarEstado();
                }

                if (estado === 422) Tads.mostrarErrores(formulario, json);
                else Tads.alertaDeError(estado, json, 'No se pudo registrar la marcación');
            });

        },
    };

    if (typeof Tads.iniciarModulos === 'function') {
        Tads.iniciarModulos();
    }
})(window);
