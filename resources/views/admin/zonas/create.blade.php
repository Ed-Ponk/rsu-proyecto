<form method="POST" action="{{ route('admin.zonas.store') }}" class="t-formulario" data-zona>
    @csrf
    @include('admin.zonas._form')
</form>
