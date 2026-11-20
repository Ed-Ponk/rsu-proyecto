<form method="POST" action="{{ route('admin.grupos.store') }}" class="t-formulario" autocomplete="off" novalidate>
    @csrf
    <x-admin.requisitos :faltan="$faltan ?? []" />
    @include('admin.grupos._form')
</form>
