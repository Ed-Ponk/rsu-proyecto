@extends('adminlte::page')

@section('title', 'Permisos de usuario')

@php
    $colores = ['view' => 't-verde', 'create' => 't-azul', 'edit' => 't-ambar', 'delete' => 't-rojo', 'extra' => 't-turquesa'];
    $iconos = ['view' => 'bi-eye-fill', 'create' => 'bi-plus-lg', 'edit' => 'bi-pencil-square', 'delete' => 'bi-trash3-fill', 'extra' => 'bi-gear-fill'];
    $soloLectura = $esAdministrador || $esPropio;
@endphp

@section('content')
    <div class="t-cabecera">
        <div>
            <h1 class="t-titulo">
                <span class="t-icono-titulo"><i class="bi bi-shield-lock-fill"></i></span>
                Permisos de usuario
            </h1>
            <p class="t-subtitulo">
                <strong>{{ trim($usuario->name.' '.$usuario->last_name) }}</strong> · {{ $usuario->email }}
                · {!! $esAdministrador ? '<span class="t-insignia t-azul">Administrador</span>' : '<span class="t-insignia t-gris">Operador</span>' !!}
            </p>
        </div>

        <a href="{{ route('admin.usuarios.index') }}" class="btn t-boton-cancelar"><i class="bi bi-arrow-left me-1"></i> Volver a Usuarios</a>
    </div>

    <form id="formPermisos"
          data-tads-modulo="permisos"
          data-url-guardar="{{ route('admin.usuarios.permisos.guardar', $usuario->id) }}"
          data-url-lista="{{ route('admin.usuarios.index') }}"
          data-total="{{ $total }}"
          data-solo-lectura="{{ $soloLectura ? '1' : '0' }}">
        @csrf
        @method('PUT')

        @if ($esAdministrador)
            <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
                <i class="bi bi-info-circle-fill mt-1"></i>
                <div>Los administradores tienen <strong>acceso total</strong>: todas las casillas están marcadas y no se pueden modificar.
                    Para limitar a una persona, cambie su tipo de usuario a <strong>Operador</strong>.</div>
            </div>
        @elseif ($esPropio)
            <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                <div>No puede modificar sus propios permisos. Pídaselo a un administrador.</div>
            </div>
        @endif

        <div class="pm-banner">
            <div class="pm-banner-texto">
                <h2><i class="bi bi-shield-check me-2"></i>Configurar permisos de acceso</h2>
                <p>Seleccione las acciones permitidas para este usuario. <span id="contadorPermisos" class="pm-contador"></span></p>
            </div>
            <label class="pm-todos">
                <input type="checkbox" id="seleccionarTodos" @disabled($soloLectura)>
                <span>Seleccionar todos</span>
            </label>
        </div>

        <div class="row g-3">
            @foreach ($modulos as $modulo)
                <div class="col-xl-6">
                    <section class="t-tarjeta pm-modulo" data-modulo="{{ $modulo['clave'] }}">
                        <header class="pm-modulo-cabecera">
                            <h3><i class="bi {{ $modulo['icono'] }} me-2"></i>{{ $modulo['titulo'] }}</h3>
                            <label class="pm-todo">
                                <input type="checkbox" class="pm-marcar-modulo" @disabled($soloLectura)>
                                <span>Todo</span>
                            </label>
                        </header>

                        <div class="pm-modulo-cuerpo">
                            @foreach ($modulo['recursos'] as $recurso)
                                <div class="pm-recurso">
                                    <h4><i class="bi {{ $recurso['icono'] }} me-2"></i>{{ $recurso['titulo'] }}</h4>
                                    <div class="pm-permisos">
                                        @foreach ($recurso['permisos'] as $permiso)
                                            @php
                                                $marcado = in_array($permiso['codigo'], $concedidos, true);
                                                $puedeDelegar = in_array($permiso['codigo'], $delegables, true);
                                            @endphp
                                            <label class="pm-permiso {{ $marcado ? 'pm-activo' : '' }} {{ ($soloLectura || ! $puedeDelegar) ? 'pm-bloqueado' : '' }}"
                                                   @if (! $soloLectura && ! $puedeDelegar) title="Usted no tiene este permiso, por eso no puede concederlo" @endif>
                                                <span class="t-insignia {{ $colores[$permiso['tipo']] }} pm-icono"><i class="bi {{ $iconos[$permiso['tipo']] }}"></i></span>
                                                <span class="pm-texto">
                                                    <strong>{{ $permiso['texto'] }}</strong>
                                                    <small>{{ $permiso['codigo'] }}</small>
                                                </span>
                                                <input type="checkbox" name="permisos[]" value="{{ $permiso['codigo'] }}"
                                                    @checked($marcado) @disabled($soloLectura || ! $puedeDelegar)>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                </div>
            @endforeach
        </div>

        @unless ($soloLectura)
            <div class="pm-barra-guardar">
                <span class="pm-contador-barra" id="contadorBarra"></span>
                <a href="{{ route('admin.usuarios.index') }}" class="btn t-boton-cancelar"><i class="bi bi-x-lg me-1"></i> Cancelar</a>
                <button type="submit" class="btn t-boton"><i class="bi bi-floppy-fill me-1"></i> Guardar permisos</button>
            </div>
        @endunless
    </form>
@stop
