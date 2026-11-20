<form method="POST" action="{{ route('admin.asistencias.store') }}" class="t-formulario" data-edicion="0">
    @csrf
    <x-admin.requisitos :faltan="$faltan ?? []" />
    @include('admin.asistencias._form')
</form>
