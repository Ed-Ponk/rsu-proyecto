@extends('adminlte::page')

@section('title', 'Tipos de personal')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-person-badge',
        'titulo' => 'Lista de tipos de personal',
        'subtitulo' => 'Funciones del personal. «Conductor» y «Ayudante» son obligatorias y no se pueden eliminar.',
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
                            <th>Personal</th>
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
        function motivoDeBloqueo(tipo) {
            if (tipo.del_sistema) return 'No se puede eliminar: es una función predefinida del sistema';
            if (tipo.personal > 0) return `No se puede eliminar: el tipo tiene ${tipo.personal} persona(s) asignada(s)`;
            return '';
        }

        new Tads.Gestor({
            entidad: 'tipo de personal',
            icono: 'bi-person-badge',
            urlListado: "{{ route('admin.tipospersonal.index') }}",
            urlRecurso: "{{ url('admin/tipos-personal') }}",
            urlCrear: "{{ route('admin.tipospersonal.create') }}",
            orden: [],
            columnas: [
                { data: 'nombre', render: (v, t, fila) => Tads.insignia(v, 'azul')
                    + (fila.del_sistema ? ' <i class="bi bi-lock-fill text-muted" data-bs-toggle="tooltip" title="Función predefinida del sistema"></i>' : '') },
                { data: 'descripcion', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                { data: 'personal', render: (v) => Tads.insignia(v, v > 0 ? 'verde' : 'gris') },
                { data: 'creado', render: Tads.celdaFecha },
                { data: 'actualizado', render: Tads.celdaFecha },
                { data: null, orderable: false, searchable: false, className: 'text-center',
                    render: (fila) => Tads.acciones(Tads.botonEditar(fila.id), Tads.botonEliminar(fila.id, fila.nombre, motivoDeBloqueo(fila))) },
            ],
        });
    </script>
@stop
