<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn t-boton']) }}>
    {{ $slot }}
</button>
