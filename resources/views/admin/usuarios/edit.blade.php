<form method="POST" action="{{ route('admin.usuarios.update', $usuario->id) }}" class="t-formulario" autocomplete="off">
    @csrf
    @method('PUT')
    <input type="hidden" name="version" value="{{ $usuario->version }}">
    @include('admin.usuarios._form')
</form>
