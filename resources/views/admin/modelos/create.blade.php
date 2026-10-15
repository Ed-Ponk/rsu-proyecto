<form method="POST" action="{{ route('admin.modelos.store') }}" class="t-formulario">
    @csrf
    @include('admin.modelos.plantilla.formulario')
</form>
