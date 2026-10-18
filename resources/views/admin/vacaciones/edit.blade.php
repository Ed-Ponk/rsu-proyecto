<form method="POST" action="{{ route('admin.vacaciones.update', $vacacion->id) }}" class="t-formulario" data-vacaciones>
    @csrf
    @method('PUT')
    @include('admin.vacaciones._form')
</form>
