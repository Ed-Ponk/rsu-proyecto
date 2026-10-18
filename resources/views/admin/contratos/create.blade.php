<form method="POST" action="{{ route('admin.contratos.store') }}" class="t-formulario">
    @csrf
    @include('admin.contratos._form')
</form>
