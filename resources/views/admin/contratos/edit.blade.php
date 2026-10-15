<form method="POST" action="{{ route('admin.contratos.update', $contrato->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    @include('admin.contratos.plantilla.formulario')
</form>
