@props(['faltan' => []])

{{-- Aviso para quien abre un formulario sin tener aún lo necesario: qué crear primero y dónde. --}}
@if (count($faltan))
    <div class="t-nota t-nota-ambar mb-3" role="note">
        <strong><i class="bi bi-info-circle-fill me-1"></i> Antes de continuar necesita registrar:</strong>
        <ul class="mb-0">
            @foreach ($faltan as $falta)
                <li>{{ $falta['texto'] }} — <a href="{{ $falta['ruta'] }}">{{ $falta['enlace'] }}</a></li>
            @endforeach
        </ul>
    </div>
@endif
