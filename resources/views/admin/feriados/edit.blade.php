<form method="POST" action="{{ route('admin.feriados.update', $feriado->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    <input type="hidden" name="version" value="{{ $feriado->version }}">
    @include('admin.feriados._form')
</form>
