<form method="POST" action="{{ route('admin.colores.update', $color->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    <input type="hidden" name="version" value="{{ $color->version }}">
    @include('admin.colores._form')
</form>
