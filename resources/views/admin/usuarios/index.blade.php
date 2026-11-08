@extends('adminlte::page')

@section('title', 'Lista de usuarios')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-people-fill',
        'titulo' => 'Lista de usuarios',
        'subtitulo' => 'Cuentas que inician sesión en el panel. El administrador tiene acceso total; el operador solo a lo que se le conceda en «Permisos».',
        'boton' => auth()->user()->can('users.create') ? 'Nuevo usuario' : null,
    ])

    <div class="t-tarjeta"
         id="moduloUsuarios"
         data-tads-modulo="usuarios"
         data-url-listado="{{ route('admin.usuarios.index') }}"
         data-url-recurso="{{ url('admin/usuarios') }}"
         data-url-crear="{{ route('admin.usuarios.create') }}"
         data-puede-crear="{{ auth()->user()->can('users.create') ? '1' : '0' }}"
         data-puede-editar="{{ auth()->user()->can('users.edit') ? '1' : '0' }}"
         data-puede-eliminar="{{ auth()->user()->can('users.delete') ? '1' : '0' }}"
         data-puede-permisos="{{ auth()->user()->can('users.manage') ? '1' : '0' }}">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Nombre</th>
                            <th>Apellidos</th>
                            <th>DNI</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Tipo de usuario</th>
                            <th>Estado</th>
                            <th>Actualización</th>
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
