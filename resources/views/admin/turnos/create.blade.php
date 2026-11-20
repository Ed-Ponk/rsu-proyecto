<form method="POST" action="{{ route('admin.turnos.store') }}" class="t-formulario">
    @csrf
    @include('admin.turnos._form')
</form>
