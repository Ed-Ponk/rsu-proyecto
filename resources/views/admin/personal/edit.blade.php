<form method="POST" action="{{ route('admin.personal.update', $persona->id) }}" class="t-formulario" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <input type="hidden" name="version" value="{{ $persona->version }}">
    @include('admin.personal._form')
</form>
