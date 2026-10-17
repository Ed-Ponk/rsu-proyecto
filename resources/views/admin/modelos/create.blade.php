<form method="POST" action="{{ route('admin.modelos.store') }}" class="t-formulario">
    @csrf
    @include('admin.modelos._form')
</form>
