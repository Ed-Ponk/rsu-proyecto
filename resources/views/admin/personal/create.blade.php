<form method="POST" action="{{ route('admin.personal.store') }}" class="t-formulario" enctype="multipart/form-data">
    @csrf
    @include('admin.personal.plantilla.formulario')
</form>
