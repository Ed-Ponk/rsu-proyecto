@extends('adminlte::page')

@section('title', 'Usuarios del sistema')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-person-gear',
        'titulo' => 'Usuarios del sistema',
        'subtitulo' => 'Administradores que pueden iniciar sesión. Siempre debe quedar al menos uno activo.',
        'boton' => 'Nuevo usuario',
    ])

    <div class="t-tarjeta"
         id="moduloUsuarios"
         data-tads-modulo="usuarios"
         data-url-listado="{{ route('admin.usuarios.index') }}"
         data-url-recurso="{{ url('admin/usuarios') }}"
         data-url-crear="{{ route('admin.usuarios.create') }}">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Estado</th>
                            <th>Verificación en 2 pasos</th>
                            <th>Creación</th>
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
