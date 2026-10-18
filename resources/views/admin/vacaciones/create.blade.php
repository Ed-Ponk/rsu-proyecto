<form method="POST" action="{{ route('admin.vacaciones.store') }}" class="t-formulario" data-vacaciones>
    @csrf
    @include('admin.vacaciones._form')
</form>
