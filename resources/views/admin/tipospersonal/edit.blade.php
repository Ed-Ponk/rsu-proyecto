<form method="POST" action="{{ route('admin.tipospersonal.update', $tipo->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    <input type="hidden" name="version" value="{{ $tipo->version }}">
    @include('admin.tipospersonal._form')
</form>
