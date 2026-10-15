<form method="POST" action="{{ route('admin.tipospersonal.update', $tipo->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    @include('admin.tipospersonal.plantilla.formulario')
</form>
