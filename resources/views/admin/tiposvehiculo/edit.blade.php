<form method="POST" action="{{ route('admin.tiposvehiculo.update', $tipo->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    <input type="hidden" name="version" value="{{ $tipo->version }}">
    @include('admin.tiposvehiculo._form')
</form>
