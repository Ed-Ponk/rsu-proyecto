<form method="POST" action="{{ route('admin.contratos.update', $contrato->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    <input type="hidden" name="version" value="{{ $contrato->version }}">
    @include('admin.contratos._form')
</form>
