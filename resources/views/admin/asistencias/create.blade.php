<form method="POST" action="{{ route('admin.asistencias.store') }}" class="t-formulario" data-edicion="0">
    @csrf
    @include('admin.asistencias.plantilla.formulario')
</form>
