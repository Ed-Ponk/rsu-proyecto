<form method="POST" action="{{ route('admin.usuarios.update', $usuario->id) }}" class="t-formulario" autocomplete="off">
    @csrf
    @method('PUT')
    @include('admin.usuarios._form')
</form>
