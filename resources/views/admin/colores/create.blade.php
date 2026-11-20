<form method="POST" action="{{ route('admin.colores.store') }}" class="t-formulario">
    @csrf
    @include('admin.colores._form')
</form>
