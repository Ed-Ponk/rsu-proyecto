<form method="POST" action="{{ route('admin.marcas.update', $marca->id) }}" class="t-formulario" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <input type="hidden" name="version" value="{{ $marca->version }}">
    @include('admin.marcas._form')
</form>
