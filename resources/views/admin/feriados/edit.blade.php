<form method="POST" action="{{ route('admin.feriados.update', $feriado->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    @include('admin.feriados._form')
</form>
