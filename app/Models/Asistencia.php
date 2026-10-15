<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    protected $table = 'attendances';

    public const PRESENTE = 'presente';

    public const AUSENTE = 'ausente';

    public const ENTRADA = 'entrada';

    public const SALIDA = 'salida';

    protected $fillable = [
        'user_id', 'date', 'time', 'schedule_id', 'type', 'status', 'source', 'notes', 'registered_by',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'user_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'schedule_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * Vuelve a numerar las marcaciones del día: la primera es entrada, la segunda salida, y así sucesivamente.
     * Se llama después de editar o borrar para que la secuencia nunca quede rota.
     */
    public static function renumerarDia(int $idPersonal, string $dia): void
    {
        $marcaciones = static::where('user_id', $idPersonal)
            ->whereDate('date', $dia)
            ->where('status', self::PRESENTE)
            ->orderBy('time')
            ->orderBy('id')
            ->get();

        foreach ($marcaciones as $posicion => $marcacion) {
            $tipo = $posicion % 2 === 0 ? self::ENTRADA : self::SALIDA;

            if ($marcacion->type !== $tipo) {
                $marcacion->forceFill(['type' => $tipo])->save();
            }
        }
    }
}
