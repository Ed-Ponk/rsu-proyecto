<form method="POST" action="{{ route('admin.colores.store') }}" class="t-formulario">
    @csrf
    @include('admin.colores.plantilla.formulario')
</form>
