{{-- Motivo de un cambio: uno predefinido y un texto que se completa solo (y se puede editar). Hace falta al menos el texto o el motivo.
     Con $campo los datos viajan como motivos[campo][...]; sin él, como motivo_id y descripcion. --}}
@php
    $campo = $campo ?? null;
    $sufijo = $campo ?? 'masivo';
    $nombre = fn (string $dato) => $campo ? "motivos[{$campo}][{$dato}]" : $dato;
    $etiqueta = $etiqueta ?? 'del cambio';
@endphp
<div data-motivo>
    <label for="motivo_{{ $sufijo }}">Motivo predefinido</label>
    <select @if ($campo === null) name="motivo_id" @endif id="motivo_{{ $sufijo }}" class="form-select" data-motivo-select>
        <option value="">Seleccione un motivo</option>
        @foreach ($motivos as $motivo)
            <option value="{{ $motivo->id }}" data-nombre="{{ $motivo->name }}" data-descripcion="{{ $motivo->description }}">{{ $motivo->name }}</option>
        @endforeach
    </select>

    <label for="detalle_{{ $sufijo }}">Motivo {{ $etiqueta }} <small>(se completará automáticamente)</small></label>
    <textarea @if ($campo === null) name="descripcion" @endif id="detalle_{{ $sufijo }}" class="form-control" rows="2" maxlength="500"
        placeholder="Ingrese el motivo {{ $etiqueta }}" data-motivo-detalle></textarea>
</div>
