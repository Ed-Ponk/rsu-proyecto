/**
 * Módulo de Personal (Tads)
 *
 * Administra el listado DataTables de trabajadores (conductores y ayudantes),
 * la regla condicional de licencia de conducir obligatoria para conductores,
 * vista previa del avatar fotográfico y máscaras numéricas para DNI y teléfono.
 */
(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    const colorDeFuncion = (funcion) => funcion === 'Conductor' ? 'azul' : (funcion === 'Ayudante' ? 'turquesa' : 'violeta');

    const botonContratos = (fila) =>
        `<button type="button" class="btn t-boton-icono t-info" data-contratos="${fila.id}" data-nombre="${Tads.escapar(fila.nombre_completo)}" data-bs-toggle="tooltip" title="Ver contratos (${fila.contratos})"><i class="bi bi-file-earmark-text"></i></button>`;

    const botonVacaciones = (fila) =>
        `<button type="button" class="btn t-boton-icono t-info" data-vacaciones="${fila.id}" data-nombre="${Tads.escapar(fila.nombre_completo)}" data-bs-toggle="tooltip" title="Ver vacaciones"><i class="bi bi-airplane-fill"></i></button>`;

    function activarFormularioDePersonal(cuerpo) {
        const tipo = cuerpo.querySelector('#personalTipoTextoId');   // campo oculto del buscador de tipo
        const idConductor = String(cuerpo.querySelector('#personalTipo')?.dataset.conductor || '');
        const bloqueLicencia = cuerpo.querySelector('#bloqueLicencia');
        const licencia = cuerpo.querySelector('#personalLicencia');

        if (tipo && bloqueLicencia && licencia) {
            function revisarLicencia() {
                const esConductor = tipo.value === idConductor;
                bloqueLicencia.classList.toggle('d-none', !esConductor);
                licencia.required = esConductor;
                licencia.disabled = !esConductor;
            }

            tipo.addEventListener('change', revisarLicencia);
            revisarLicencia();
        }

        const cajaFoto = cuerpo.querySelector('#cajaFoto');
        const archivoFoto = cuerpo.querySelector('#archivoFoto');
        if (cajaFoto && archivoFoto) {
            Tads.vistaPreviaArchivo(cajaFoto, archivoFoto);
        }

        // DNI y teléfono aceptan solo dígitos.
        cuerpo.querySelectorAll('#personalDni, #personalTelefono').forEach((campo) => {
            campo.addEventListener('input', () => { campo.value = campo.value.replace(/\D/g, ''); });
        });
    }

    Tads.Modulos.personal = {
        init: function (contenedor) {
            const urlListado = contenedor.dataset.urlListado;
            const urlRecurso = contenedor.dataset.urlRecurso;
            const urlCrear = contenedor.dataset.urlCrear;

            const gestorPersonal = new Tads.Gestor({
                entidad: 'personal',
                icono: 'bi-person-lines-fill',
                urlListado: urlListado,
                urlRecurso: urlRecurso,
                urlCrear: urlCrear,
                orden: [[3, 'asc']],
                alCargarFormulario: activarFormularioDePersonal,
                columnas: [
                    { data: 'foto', orderable: false, searchable: false, render: (foto, tipo, fila) => foto
                        ? `<img class="t-avatar" src="${Tads.escapar(foto)}" alt="Foto">`
                        : `<span class="t-avatar">${Tads.escapar(fila.iniciales)}</span>` },
                    { data: 'dni', render: (v) => `<span class="t-codigo">${Tads.escapar(v)}</span>` },
                    { data: 'nombres' },
                    { data: 'apellidos', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                    { data: 'correo' },
                    { data: 'funcion', render: (v) => Tads.insignia(v, colorDeFuncion(v)) },
                    { data: 'activo', render: (v) => v ? Tads.insignia('Activo', 'verde') : Tads.insignia('Inactivo', 'rojo') },
                    { data: 'con_contrato', render: (v) => v ? Tads.insignia('Disponible', 'verde') : Tads.insignia('Sin contrato', 'ambar') },
                    { data: 'creado', render: Tads.celdaFecha },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => Tads.acciones(botonContratos(fila), botonVacaciones(fila), Tads.botonEditar(fila.id), Tads.botonEliminar(fila.id, fila.nombre_completo)) },
                ],
            });

            contenedor._gestor = gestorPersonal;

            document.getElementById('tabla')?.addEventListener('click', (evento) => {
                const vacaciones = evento.target.closest('[data-vacaciones]');
                if (vacaciones) {
                    bootstrap.Tooltip.getInstance(vacaciones)?.hide();
                    return gestorPersonal.abrirFormulario(
                        `${urlRecurso}/${vacaciones.dataset.vacaciones}/vacaciones`,
                        `<i class="bi bi-airplane-fill me-2"></i>Vacaciones de ${Tads.escapar(vacaciones.dataset.nombre)}`,
                    );
                }

                const boton = evento.target.closest('[data-contratos]');
                if (!boton) return;
                bootstrap.Tooltip.getInstance(boton)?.hide();
                gestorPersonal.abrirFormulario(
                    `${urlRecurso}/${boton.dataset.contratos}/contratos`,
                    `<i class="bi bi-file-earmark-text me-2"></i>Contratos de ${Tads.escapar(boton.dataset.nombre)}`,
                );
            });
        },
    };

    if (typeof Tads.iniciarModulos === 'function') {
        Tads.iniciarModulos();
    }
})(window);
