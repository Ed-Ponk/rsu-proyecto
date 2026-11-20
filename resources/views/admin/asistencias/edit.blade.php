<form method="POST" action="{{ route('admin.asistencias.update', $asistencia->id) }}" class="t-formulario" data-edicion="1">
    @csrf
    @method('PUT')
    @include('admin.asistencias._form')
</form>
