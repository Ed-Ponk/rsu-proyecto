<form method="POST" action="{{ route('admin.marcas.update', $marca->id) }}" class="t-formulario" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('admin.marcas._form')
</form>
