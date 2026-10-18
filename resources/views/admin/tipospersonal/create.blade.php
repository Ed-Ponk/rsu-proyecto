<form method="POST" action="{{ route('admin.tipospersonal.store') }}" class="t-formulario">
    @csrf
    @include('admin.tipospersonal._form')
</form>
