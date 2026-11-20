<form method="POST" action="{{ route('admin.zonas.store') }}" class="t-formulario" data-zona enctype="multipart/form-data">
    @csrf
    @include('admin.zonas._form')
</form>
