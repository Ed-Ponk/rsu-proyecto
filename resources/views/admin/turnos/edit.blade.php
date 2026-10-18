<form method="POST" action="{{ route('admin.turnos.update', $turno->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    @include('admin.turnos._form')
</form>
