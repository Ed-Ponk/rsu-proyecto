<form method="POST" action="{{ route('admin.vehiculos.store') }}" class="t-formulario">
    @csrf
    <x-admin.requisitos :faltan="$faltan ?? []" />
    @include('admin.vehiculos._form')
</form>
