<form method="POST" action="{{ route('admin.modelos.update', $modelo->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    <input type="hidden" name="version" value="{{ $modelo->version }}">
    @include('admin.modelos._form')
</form>
