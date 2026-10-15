@extends('adminlte::page')

@section('title', 'Tipos de vehículo')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-truck-flatbed',
        'titulo' => 'Lista de tipos de vehículo',
        'subtitulo' => 'Clasificación de la flota: camión recolector, compactador, volquete...',
        'boton' => 'Nuevo tipo',
    ])

    <div class="t-tarjeta">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
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

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-md'])
@stop

@section('js')
    <script>
        new Tads.Gestor({
            entidad: 'tipo de vehículo',
            icono: 'bi-truck-flatbed',
            urlListado: "{{ route('admin.tiposvehiculo.index') }}",
            urlRecurso: "{{ url('admin/tipos-vehiculo') }}",
            urlCrear: "{{ route('admin.tiposvehiculo.create') }}",
            orden: [[0, 'asc']],
            columnas: [
                { data: 'nombre', render: (v) => Tads.insignia(v, 'azul') },
                { data: 'descripcion', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                { data: 'vehiculos', render: (v) => Tads.insignia(v, v > 0 ? 'azul' : 'gris') },
                { data: 'creado', render: Tads.celdaFecha },
                { data: 'actualizado', render: Tads.celdaFecha },
                { data: null, orderable: false, searchable: false, className: 'text-center',
                    render: (fila) => Tads.acciones(
                        Tads.botonEditar(fila.id),
                        Tads.botonEliminar(fila.id, fila.nombre, fila.vehiculos > 0 ? `No se puede eliminar: el tipo tiene ${fila.vehiculos} vehículo(s) asignado(s)` : ''),
                    ) },
            ],
        });
    </script>
@stop
