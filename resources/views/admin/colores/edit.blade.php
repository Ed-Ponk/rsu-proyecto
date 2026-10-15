<form method="POST" action="{{ route('admin.colores.update', $color->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    @include('admin.colores.plantilla.formulario')
</form>
