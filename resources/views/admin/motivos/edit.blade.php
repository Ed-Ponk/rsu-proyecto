<form method="POST" action="{{ route('admin.motivos.update', $motivo->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    @include('admin.motivos._form')
</form>
