<form method="POST" action="{{ route('admin.usuarios.store') }}" class="t-formulario" enctype="multipart/form-data" autocomplete="off">
    @csrf
    @include('admin.usuarios._form')
</form>
