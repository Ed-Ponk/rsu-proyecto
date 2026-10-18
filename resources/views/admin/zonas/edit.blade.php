<form method="POST" action="{{ route('admin.zonas.update', $zona->id) }}" class="t-formulario" data-zona>
    @csrf
    @method('PUT')
    @include('admin.zonas._form')
</form>
