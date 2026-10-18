<form method="POST" action="{{ route('admin.marcas.store') }}" class="t-formulario" enctype="multipart/form-data">
    @csrf
    @include('admin.marcas._form')
</form>
