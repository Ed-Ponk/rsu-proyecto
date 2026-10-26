<form method="POST" action="{{ route('admin.grupos.update', $grupo->id) }}" class="t-formulario" autocomplete="off" novalidate>
    @csrf
    @method('PUT')
    @include('admin.grupos._form')
</form>
