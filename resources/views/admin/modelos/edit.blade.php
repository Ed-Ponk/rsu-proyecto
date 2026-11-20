<form method="POST" action="{{ route('admin.modelos.update', $modelo->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    @include('admin.modelos._form')
</form>
