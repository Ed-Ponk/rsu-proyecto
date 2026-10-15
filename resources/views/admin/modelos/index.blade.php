@extends('adminlte::page')

@section('title', 'Modelos')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-wrench-adjustable',
        'titulo' => 'Lista de modelos',
        'subtitulo' => 'Modelos de vehículo; cada uno pertenece a una marca.',
        'boton' => 'Nuevo modelo',
    ])

    <div class="t-tarjeta">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Código</th>
                            <th>Marca</th>
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
            entidad: 'modelo',
            icono: 'bi-wrench-adjustable',
            urlListado: "{{ route('admin.modelos.index') }}",
            urlRecurso: "{{ url('admin/modelos') }}",
            urlCrear: "{{ route('admin.modelos.create') }}",
            orden: [[0, 'asc']],
            columnas: [
                { data: 'nombre', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                { data: 'codigo', render: (v) => `<span class="t-codigo">${Tads.escapar(v)}</span>` },
                { data: 'marca', render: (marca, tipo, fila) =>
                    `<span class="d-inline-flex align-items-center gap-2"><img class="t-logo-mini" style="width:34px;height:26px" src="${Tads.escapar(fila.logo)}" alt="">${Tads.escapar(marca)}</span>` },
                { data: 'descripcion', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                { data: 'vehiculos', render: (v) => Tads.insignia(v, v > 0 ? 'azul' : 'gris') },
                { data: 'creado', render: Tads.celdaFecha },
                { data: 'actualizado', render: Tads.celdaFecha },
                { data: null, orderable: false, searchable: false, className: 'text-center',
                    render: (fila) => Tads.acciones(
                        Tads.botonEditar(fila.id),
                        Tads.botonEliminar(fila.id, fila.nombre, fila.vehiculos > 0 ? `No se puede eliminar: el modelo tiene ${fila.vehiculos} vehículo(s) asignado(s)` : ''),
                    ) },
            ],
        });
    </script>
@stop
