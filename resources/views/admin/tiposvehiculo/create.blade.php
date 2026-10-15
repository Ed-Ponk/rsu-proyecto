<form method="POST" action="{{ route('admin.tiposvehiculo.store') }}" class="t-formulario">
    @csrf
    @include('admin.tiposvehiculo.plantilla.formulario')
</form>
