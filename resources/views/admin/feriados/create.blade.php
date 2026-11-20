<form method="POST" action="{{ route('admin.feriados.store') }}" class="t-formulario">
    @csrf
    @include('admin.feriados._form')
</form>
