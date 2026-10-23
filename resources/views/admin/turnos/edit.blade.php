<form method="POST" action="{{ route('admin.turnos.update', $turno->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    <input type="hidden" name="version" value="{{ $turno->version }}">
    @include('admin.turnos._form')
</form>
