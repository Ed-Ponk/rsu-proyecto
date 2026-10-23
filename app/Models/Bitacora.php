<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/** Registro de cambios: quién creó, modificó o eliminó cada registro importante. */
class Bitacora extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $fillable = ['user_id', 'actor_name', 'created_at', 'action', 'model', 'model_id', 'before', 'after'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }

    /** Campos que nunca se guardan en la bitácora. */
    private const OMITIDOS = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'created_at', 'updated_at', 'version'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function registrar(string $accion, Model $modelo, ?array $antes, ?array $despues): void
    {
        if ($accion === 'modificado' && blank($despues)) {
            return;   // un "guardar" sin cambios no ensucia la bitácora
        }

        static::create([
            'user_id' => Auth::id() && User::whereKey(Auth::id())->exists() ? Auth::id() : null,
            'actor_name' => Auth::user()?->name ?? 'Sistema',
            'created_at' => now(),
            'action' => $accion,
            'model' => class_basename($modelo),
            'model_id' => (int) $modelo->getKey(),
            'before' => $antes === null ? null : Arr::except($antes, self::OMITIDOS),
            'after' => $despues === null ? null : Arr::except($despues, self::OMITIDOS),
        ]);
    }
}
