<form method="POST" action="{{ route('admin.tiposvehiculo.update', $tipo->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    @include('admin.tiposvehiculo.plantilla.formulario')
</form>
