@props(['for'])

@error($for)
    <div {{ $attributes->merge(['class' => 'invalid-feedback t-error d-block']) }}>{{ $message }}</div>
@enderror
