@extends('adminlte::page')

@section('title', 'Marcas')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-tags',
        'titulo' => 'Lista de marcas',
        'subtitulo' => 'Marcas de los vehículos que se usan en la recolección.',
        'boton' => 'Nueva marca',
    ])

    <div class="t-tarjeta">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Logo</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Modelos</th>
                            <th>Vehículos</th>
                            <th>Creación</th>
                            <th>Actualización</th>
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
        function activarVistaPreviaDelLogo(cuerpo) {
            Tads.vistaPreviaArchivo(cuerpo.querySelector('#cajaLogo'), cuerpo.querySelector('#archivoLogo'));
        }

        // Una marca no se puede borrar si tiene vehículos o modelos: el botón explica el motivo.
        function motivoDeBloqueo(marca) {
            if (marca.vehiculos > 0) return `No se puede eliminar: la marca tiene ${marca.vehiculos} vehículo(s) asignado(s)`;
            if (marca.modelos > 0) return `No se puede eliminar: la marca tiene ${marca.modelos} modelo(s) registrado(s)`;
            return '';
        }

        new Tads.Gestor({
            entidad: 'marca',
            icono: 'bi-tags',
            urlListado: "{{ route('admin.marcas.index') }}",
            urlRecurso: "{{ url('admin/marcas') }}",
            urlCrear: "{{ route('admin.marcas.create') }}",
            orden: [[1, 'asc']],
            alCargarFormulario: activarVistaPreviaDelLogo,
            columnas: [
                { data: 'logo', orderable: false, searchable: false,
                    render: (url) => `<img class="t-logo-mini" src="${Tads.escapar(url)}" alt="Logo">` },
                { data: 'nombre', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                { data: 'descripcion', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                { data: 'modelos', render: (v) => Tads.insignia(v, v > 0 ? 'violeta' : 'gris') },
                { data: 'vehiculos', render: (v) => Tads.insignia(v, v > 0 ? 'azul' : 'gris') },
                { data: 'creado', render: Tads.celdaFecha },
                { data: 'actualizado', render: Tads.celdaFecha },
                { data: null, orderable: false, searchable: false, className: 'text-center',
                    render: (fila) => Tads.acciones(Tads.botonEditar(fila.id), Tads.botonEliminar(fila.id, fila.nombre, motivoDeBloqueo(fila))) },
            ],
        });
    </script>
@stop
