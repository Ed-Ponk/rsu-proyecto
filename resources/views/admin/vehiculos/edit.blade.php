<form method="POST" action="{{ route('admin.vehiculos.update', $vehiculo->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    @include('admin.vehiculos._form')
</form>
