<form method="POST" action="{{ route('admin.vehiculos.update', $vehiculo->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    <input type="hidden" name="version" value="{{ $vehiculo->version }}">
    @include('admin.vehiculos._form')
</form>
