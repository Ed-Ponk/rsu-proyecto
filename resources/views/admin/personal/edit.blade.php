<form method="POST" action="{{ route('admin.personal.update', $persona->id) }}" class="t-formulario" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('admin.personal.plantilla.formulario')
</form>
