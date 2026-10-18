<button {{ $attributes->merge(['type' => 'button', 'class' => 'btn btn-danger rounded-3 fw-semibold']) }}>
    {{ $slot }}
</button>
