<form method="POST" action="{{ route('admin.contratos.store') }}" class="t-formulario">
    @csrf
    <x-admin.requisitos :faltan="$faltan ?? []" />
    @include('admin.contratos._form')
</form>
