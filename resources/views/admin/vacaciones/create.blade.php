<form method="POST" action="{{ route('admin.vacaciones.store') }}" class="t-formulario" data-vacaciones>
    @csrf
    <x-admin.requisitos :faltan="$faltan ?? []" />
    @include('admin.vacaciones._form')
</form>
