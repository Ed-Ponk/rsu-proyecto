<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un cambio hecho a una programación: qué se cambió, de qué valor a cuál, por qué motivo y quién lo hizo. */
class Cambio extends Model
{
    protected $table = 'programming_changes';

    public const TURNO = 'turno';

    public const VEHICULO = 'vehiculo';

    public const CONDUCTOR = 'conductor';

    public const AYUDANTE = 'ayudante';

    public const INDIVIDUAL = 'individual';

    public const MASIVO = 'masivo';

    public const TIPOS = [self::TURNO => 'Turno', self::VEHICULO => 'Vehículo', self::CONDUCTOR => 'Conductor', self::AYUDANTE => 'Ocupante'];

    protected $fillable = [
        'programming_id', 'group_id', 'type', 'old_id', 'new_id', 'old_label', 'new_label', 'old_data', 'new_data',
        'reason_id', 'reason_name', 'description', 'batch_id', 'scope', 'user_id',
    ];

    protected function casts(): array
    {
        return ['old_data' => 'array', 'new_data' => 'array'];
    }

    public function programacion(): BelongsTo
    {
        return $this->belongsTo(Programacion::class, 'programming_id');
    }

    public function motivo(): BelongsTo
    {
        return $this->belongsTo(Motivo::class, 'reason_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getTipoTextoAttribute(): string
    {
        return self::TIPOS[$this->type] ?? $this->type;
    }

    /**
     * Cómo se muestra uno de los dos valores del cambio: título y detalle (para las listas) y filas rotuladas (para el detalle).
     *
     * @return array{titulo: string, detalle: string, filas: list<array{0: string, 1: string}>}
     */
    public function valor(string $lado): array
    {
        $datos = (array) ($lado === 'old' ? $this->old_data : $this->new_data);
        $texto = $lado === 'old' ? $this->old_label : $this->new_label;

        return match ($this->type) {
            self::TURNO => [
                'titulo' => $datos['nombre'] ?? $texto, 'detalle' => ($datos['inicio'] ?? '').' - '.($datos['fin'] ?? ''),
                'filas' => [['Turno', $datos['nombre'] ?? $texto], ['Hora de entrada', $datos['inicio'] ?? '—'], ['Hora de salida', $datos['fin'] ?? '—']],
            ],
            self::VEHICULO => [
                'titulo' => $datos['nombre'] ?? $texto, 'detalle' => $datos['placa'] ?? '',
                'filas' => [['Vehículo', $datos['nombre'] ?? $texto], ['Placa', $datos['placa'] ?? '—']],
            ],
            default => [
                'titulo' => $texto, 'detalle' => isset($datos['dni']) ? 'DNI '.$datos['dni'] : '',
                'filas' => [[$this->type === self::CONDUCTOR ? 'Conductor' : 'Ocupante', $texto], ['DNI', $datos['dni'] ?? '—'], ['Función', $datos['funcion'] ?? '—']],
            ],
        };
    }

    /** El turno, vehículo o persona al que se refiere un id según el tipo de cambio. */
    public static function modeloDe(string $tipo, int $id): Turno|Vehiculo|Personal|null
    {
        return match ($tipo) {
            self::TURNO => Turno::find($id),
            self::VEHICULO => Vehiculo::find($id),
            default => Personal::with('tipo')->find($id),
        };
    }

    /** @return array{0: string, 1: array<string, mixed>} texto corto y detalle de un turno, vehículo o persona */
    public static function describir(Turno|Vehiculo|Personal|null $modelo): array
    {
        return match (true) {
            $modelo instanceof Turno => ["{$modelo->name} (".substr($modelo->time_start, 0, 5).' - '.substr($modelo->time_end, 0, 5).')', ['nombre' => $modelo->name, 'inicio' => substr($modelo->time_start, 0, 5), 'fin' => substr($modelo->time_end, 0, 5)]],
            $modelo instanceof Vehiculo => ["{$modelo->name} ({$modelo->plate})", ['nombre' => $modelo->name, 'placa' => $modelo->plate]],
            $modelo instanceof Personal => [$modelo->nombre_completo, ['dni' => $modelo->dni, 'funcion' => $modelo->tipo?->name]],
            default => ['—', []],
        };
    }

    /**
     * Guarda un cambio ya aplicado a una programación.
     *
     * @param  array{id?: int|string|null, descripcion?: string|null}  $motivo
     */
    public static function registrar(Programacion $programacion, string $tipo, Turno|Vehiculo|Personal|null $antes, Turno|Vehiculo|Personal|null $despues, array $motivo, string $lote, ?int $usuarioId, string $origen = self::INDIVIDUAL): self
    {
        [$textoAntes, $datosAntes] = self::describir($antes);
        [$textoDespues, $datosDespues] = self::describir($despues);
        $idMotivo = $motivo['id'] ?? $motivo['motivo_id'] ?? null;
        $elegido = filled($idMotivo) ? Motivo::find($idMotivo) : null;

        return self::create([
            'programming_id' => $programacion->id,
            'group_id' => $programacion->group_id,
            'type' => $tipo,
            'old_id' => $antes?->getKey(),
            'new_id' => $despues?->getKey(),
            'old_label' => $textoAntes,
            'new_label' => $textoDespues,
            'old_data' => $datosAntes,
            'new_data' => $datosDespues,
            'reason_id' => $elegido?->id,
            'reason_name' => $elegido?->name,
            'description' => filled($motivo['descripcion'] ?? null) ? trim($motivo['descripcion']) : null,
            'batch_id' => $lote,
            'scope' => $origen,
            'user_id' => $usuarioId ?? auth()->id(),
        ]);
    }
}
