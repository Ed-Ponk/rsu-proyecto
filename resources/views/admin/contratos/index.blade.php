@extends('adminlte::page')

@section('title', 'Contratos')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-file-earmark-text',
        'titulo' => 'Lista de contratos',
        'subtitulo' => 'Los contratos vigentes determinan qué personal puede asignarse en las programaciones.',
        'boton' => 'Nuevo contrato',
    ])

    <div class="t-tarjeta">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>DNI</th>
                            <th>Empleado</th>
                            <th>Tipo de contrato</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Salario</th>
                            <th>Posición</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-lg'])
@stop

@section('js')
    <script>
        // La fecha de fin solo se llena en los contratos temporales.
        function activarFormularioDeContrato(cuerpo) {
            const tipo = cuerpo.querySelector('#contratoTipo');
            const inicio = cuerpo.querySelector('#contratoInicio');
            const fin = cuerpo.querySelector('#contratoFin');
            const ayuda = cuerpo.querySelector('#ayudaFin');

            function revisarTipo() {
                const esTemporal = tipo.value === 'temporal';
                fin.disabled = !esTemporal;
                fin.required = esTemporal;
                if (!esTemporal) fin.value = '';
                ayuda.textContent = esTemporal
                    ? 'Obligatoria en los contratos temporales y posterior al inicio.'
                    : 'No aplica: los contratos nombrados y permanentes no tienen fecha de fin.';
            }

            tipo.addEventListener('change', revisarTipo);
            inicio.addEventListener('change', () => { if (inicio.value) fin.min = inicio.value; });
            revisarTipo();
        }

        const colorDeEstado = { 'Vigente': 'verde', 'Vencido': 'rojo', 'Inactivo': 'gris', 'Por iniciar': 'azul' };
        const colorDeTipo = { temporal: 'ambar', nombrado: 'violeta', permanente: 'azul' };

        const gestorContratos = new Tads.Gestor({
            entidad: 'contrato',
            icono: 'bi-file-earmark-text',
            urlListado: "{{ route('admin.contratos.index') }}",
            urlRecurso: "{{ url('admin/contratos') }}",
            urlCrear: "{{ route('admin.contratos.create') }}",
            orden: [],
            alCargarFormulario: activarFormularioDeContrato,
            columnas: [
                { data: 'dni', render: (v) => `<span class="t-codigo">${Tads.escapar(v)}</span>` },
                { data: 'persona', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                { data: 'tipo_texto', render: (texto, t, fila) => Tads.insignia(texto, colorDeTipo[fila.tipo] ?? 'gris') },
                { data: 'inicio' },
                { data: 'fin', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                { data: 'salario', render: (v) => `<span class="t-codigo">S/ ${Tads.escapar(v)}</span>` },
                { data: 'funcion', render: (v) => Tads.insignia(v, v === 'Conductor' ? 'azul' : 'turquesa') },
                { data: 'estado', render: (v) => Tads.insignia(v, colorDeEstado[v] ?? 'gris') },
                { data: null, orderable: false, searchable: false, className: 'text-center', render: (fila) => {
                    // Los contratos no se borran: se activan o desactivan para conservar el historial.
                    const cambiar = fila.activo
                        ? `<button type="button" class="btn t-boton-icono t-neutro" data-cambiar="${fila.id}" data-activo="1" data-bs-toggle="tooltip" title="Desactivar contrato"><i class="bi bi-toggle-on"></i></button>`
                        : `<button type="button" class="btn t-boton-icono t-exito" data-cambiar="${fila.id}" data-activo="0" data-bs-toggle="tooltip" title="Activar contrato"><i class="bi bi-toggle-off"></i></button>`;
                    return Tads.acciones(Tads.botonEditar(fila.id), cambiar);
                } },
            ],
        });

        document.getElementById('tabla').addEventListener('click', async (evento) => {
            const boton = evento.target.closest('[data-cambiar]');
            if (!boton) return;

            const estabaActivo = boton.dataset.activo === '1';

            const decision = await Swal.fire({
                title: estabaActivo ? '¿Desactivar contrato?' : '¿Activar contrato?',
                text: estabaActivo
                    ? 'La persona dejará de estar disponible para las programaciones.'
                    : 'La persona volverá a estar disponible para las programaciones.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: estabaActivo ? 'Sí, desactivar' : 'Sí, activar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
            });

            if (!decision.isConfirmed) return;

            const { ok, estado, json } = await Tads.peticion(`{{ url('admin/contratos') }}/${boton.dataset.cambiar}/estado`, {
                metodo: 'POST',
                cuerpo: Tads.cuerpoConMetodo('PATCH'),
            });

            if (!ok) return Tads.alertaDeError(estado, json, 'No se pudo cambiar el estado');

            gestorContratos.recargar();
            Tads.aviso(json.mensaje);
        });
    </script>
@stop
