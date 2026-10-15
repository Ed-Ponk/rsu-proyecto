@extends('adminlte::page')

@section('title', 'Colores')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-palette',
        'titulo' => 'Lista de colores',
        'subtitulo' => 'Colores que se asignan a los vehículos de la flota.',
        'boton' => 'Nuevo color',
    ])

    <div class="t-tarjeta">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Color</th>
                            <th>Nombre</th>
                            <th>Código</th>
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
        // Mantiene sincronizados el selector de color, el código hexadecimal y la vista previa.
        function activarSelectorDeColor(cuerpo) {
            const selector = cuerpo.querySelector('#selectorColor');
            const campoCodigo = cuerpo.querySelector('#colorCodigo');
            const vista = cuerpo.querySelector('#vistaColor');
            const esHexadecimal = (texto) => /^#[0-9A-Fa-f]{6}$/.test(texto);

            const pintar = (codigo) => {
                if (!esHexadecimal(codigo)) return;
                vista.style.backgroundColor = codigo;
                vista.textContent = codigo.toUpperCase();
            };

            selector.addEventListener('input', () => {
                campoCodigo.value = selector.value.toUpperCase();
                pintar(selector.value);
            });

            campoCodigo.addEventListener('input', () => {
                let texto = campoCodigo.value.trim();
                if (texto && texto[0] !== '#') texto = '#' + texto;
                if (esHexadecimal(texto)) {
                    selector.value = texto;
                    pintar(texto);
                }
            });

            pintar(campoCodigo.value);
        }

        new Tads.Gestor({
            entidad: 'color',
            icono: 'bi-palette',
            urlListado: "{{ route('admin.colores.index') }}",
            urlRecurso: "{{ url('admin/colores') }}",
            urlCrear: "{{ route('admin.colores.create') }}",
            orden: [[1, 'asc']],
            alCargarFormulario: activarSelectorDeColor,
            columnas: [
                { data: 'codigo', orderable: false, searchable: false,
                    render: (codigo) => `<span class="t-muestra-color" style="background-color:${Tads.escapar(codigo)}"></span>` },
                { data: 'nombre', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                { data: 'codigo', render: (v) => `<span class="t-codigo">${Tads.escapar(v)}</span>` },
                { data: 'descripcion', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                { data: 'vehiculos', render: (v) => Tads.insignia(v, v > 0 ? 'azul' : 'gris') },
                { data: 'creado', render: Tads.celdaFecha },
                { data: 'actualizado', render: Tads.celdaFecha },
                { data: null, orderable: false, searchable: false, className: 'text-center',
                    render: (fila) => Tads.acciones(
                        Tads.botonEditar(fila.id),
                        Tads.botonEliminar(fila.id, fila.nombre, fila.vehiculos > 0 ? `No se puede eliminar: el color tiene ${fila.vehiculos} vehículo(s) asignado(s)` : ''),
                    ) },
            ],
        });
    </script>
@stop
