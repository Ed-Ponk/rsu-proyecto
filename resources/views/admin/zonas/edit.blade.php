<form method="POST" action="{{ route('admin.zonas.update', $zona->id) }}" class="t-formulario" data-zona>
    @csrf
    @method('PUT')
    <input type="hidden" name="version" value="{{ $zona->version }}">
    @include('admin.zonas._form')
</form>
