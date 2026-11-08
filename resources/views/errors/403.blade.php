@extends('adminlte::page')

@section('title', 'Sin permiso')

@section('content')
    <div class="t-tarjeta mx-auto" style="max-width: 560px;">
        <div class="t-tarjeta-cuerpo text-center py-5">
            <div class="t-icono-titulo justify-content-center mb-2" style="font-size: 3rem;"><i class="bi bi-shield-lock-fill"></i></div>
            <h1 class="h4 fw-bold mb-2">No tiene permiso para esta acción</h1>
            <p class="text-muted mb-4">{{ $exception->getMessage() ?: 'Solicite el permiso a un administrador del sistema.' }}</p>
            <a href="{{ route('admin.inicio') }}" class="btn t-boton"><i class="bi bi-house-door me-1"></i> Volver al inicio</a>
        </div>
    </div>
@stop
