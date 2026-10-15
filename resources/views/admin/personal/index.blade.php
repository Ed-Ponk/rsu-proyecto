@extends('adminlte::page')

@section('title', 'Personal')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-person-lines-fill',
        'titulo' => 'Lista de personal',
        'subtitulo' => 'Conductores y ayudantes. Solo el personal activo y con contrato vigente puede programarse.',
        'boton' => 'Nuevo personal',
    ])

    <div class="t-tarjeta">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>DNI</th>
                            <th>Nombres</th>
                            <th>Apellidos</th>
                            <th>Email</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Contrato</th>
                            <th>Creación</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-xl'])
@stop

@section('js')
    <script>
        function activarFormularioDePersonal(cuerpo) {
            const tipo = cuerpo.querySelector('#personalTipo');
            const idConductor = String(tipo.dataset.conductor);
            const bloqueLicencia = cuerpo.querySelector('#bloqueLicencia');
            const licencia = cuerpo.querySelector('#personalLicencia');

            // La licencia de conducir solo se pide cuando la función elegida es "Conductor".
            function revisarLicencia() {
                const esConductor = tipo.value === idConductor;
                bloqueLicencia.classList.toggle('d-none', !esConductor);
                licencia.required = esConductor;
                licencia.disabled = !esConductor;
            }

            tipo.addEventListener('change', revisarLicencia);
            revisarLicencia();

            Tads.vistaPreviaArchivo(cuerpo.querySelector('#cajaFoto'), cuerpo.querySelector('#archivoFoto'));

            // DNI y teléfono aceptan solo dígitos.
            cuerpo.querySelectorAll('#personalDni, #personalTelefono').forEach((campo) => {
                campo.addEventListener('input', () => { campo.value = campo.value.replace(/\D/g, ''); });
            });
        }

        const colorDeFuncion = (funcion) => funcion === 'Conductor' ? 'azul' : (funcion === 'Ayudante' ? 'turquesa' : 'violeta');

        new Tads.Gestor({
            entidad: 'personal',
            icono: 'bi-person-lines-fill',
            urlListado: "{{ route('admin.personal.index') }}",
            urlRecurso: "{{ url('admin/personal') }}",
            urlCrear: "{{ route('admin.personal.create') }}",
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
                    render: (fila) => Tads.acciones(Tads.botonEditar(fila.id), Tads.botonEliminar(fila.id, fila.nombre_completo)) },
            ],
        });
    </script>
@stop
