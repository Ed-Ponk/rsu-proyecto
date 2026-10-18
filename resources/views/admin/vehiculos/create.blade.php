<form method="POST" action="{{ route('admin.vehiculos.store') }}" class="t-formulario">
    @csrf
    @include('admin.vehiculos._form')
</form>
