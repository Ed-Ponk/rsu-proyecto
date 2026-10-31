<form method="POST" action="{{ route('admin.motivos.store') }}" class="t-formulario">
    @csrf
    @include('admin.motivos._form')
</form>
