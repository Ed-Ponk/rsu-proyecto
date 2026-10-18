@props(['on'])

<div x-data="{ shown: false, timeout: null }"
    x-init="@this.on('{{ $on }}', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => { shown = false }, 2500); })"
    x-show.transition.out.opacity.duration.1500ms="shown"
    x-transition:leave.opacity.duration.1500ms
    style="display: none;"
    {{ $attributes->merge(['class' => 'small fw-semibold text-success']) }}>
    <i class="bi bi-check-circle-fill me-1"></i>{{ $slot->isEmpty() ? 'Guardado.' : $slot }}
</div>
