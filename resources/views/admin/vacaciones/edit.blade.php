<form method="POST" action="{{ route('admin.vacaciones.update', $vacacion->id) }}" class="t-formulario" data-vacaciones>
    @csrf
    @method('PUT')
    <input type="hidden" name="version" value="{{ $vacacion->version }}">
    @include('admin.vacaciones._form')
</form>
