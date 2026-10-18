<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Conductores y ayudantes. Comparten la tabla `users` con los usuarios del sistema (así lo dibuja el docente en su
 * diagrama); lo que los distingue es tener `usertype_id`. Un filtro global evita mezclarlos con los administradores.
 */
class Personal extends Model
{
    protected $table = 'users';

    /** Licencia de conducir: una letra y ocho dígitos (Q12345678). */
    public const FORMATO_LICENCIA = '/^[A-Z][0-9]{8}$/';

    protected $fillable = [
        'name', 'last_name', 'dni', 'birthdate', 'phone', 'email', 'address',
        'license', 'password', 'profile_photo_path', 'status', 'usertype_id',
    ];

    protected $hidden = ['password'];

    protected static function booted(): void
    {
        static::addGlobalScope('solo_personal', function (Builder $consulta) {
            $consulta->whereNotNull('users.usertype_id');
        });
    }

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'status' => 'boolean',
            'password' => 'hashed',   // la contraseña es la clave de marcación
        ];
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoPersonal::class, 'usertype_id');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'user_id')->latest('start_date');
    }

    public function contratoVigente(): HasOne
    {
        return $this->hasOne(Contrato::class, 'user_id')->vigente();
    }

    public function vacaciones(): HasMany
    {
        return $this->hasMany(Vacacion::class, 'user_id')->latest('start_date');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'user_id');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->name} {$this->last_name}");
    }

    public function getUrlFotoAttribute(): ?string
    {
        $ruta = $this->profile_photo_path;

        if (blank($ruta)) {
            return null;
        }

        return preg_match('#^(https?://|/)#', $ruta) ? $ruta : asset($ruta);
    }

    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('users.status', true);
    }

    /** Quién puede entrar en una programación: activo, con contrato vigente y sin vacaciones aprobadas en la fecha. */
    public function scopeDisponibles(Builder $consulta, ?Carbon $fecha = null): Builder
    {
        $fecha ??= today();

        return $consulta->activos()
            ->whereHas('contratos', fn (Builder $c) => $c->vigente($fecha))
            ->whereDoesntHave('vacaciones', fn (Builder $v) => $v
                ->where('status', Vacacion::APROBADA)
                ->queSeCruzaCon($fecha, $fecha));
    }

    public function scopeAsistioEl(Builder $consulta, Carbon|string $fecha): Builder
    {
        $dia = Carbon::parse($fecha)->toDateString();

        return $consulta->whereHas('asistencias', fn (Builder $c) => $c
            ->whereDate('date', $dia)
            ->where('status', Asistencia::PRESENTE));
    }

    /** Personal de reserva para cubrir a otro: misma función, contrato vigente y que ya marcó ese día. */
    public function scopeReemplazosDe(Builder $consulta, self $persona, Carbon|string|null $fecha = null): Builder
    {
        $fecha = $fecha ? Carbon::parse($fecha) : today();

        return $consulta->disponibles($fecha)
            ->asistioEl($fecha)
            ->where('users.usertype_id', $persona->usertype_id)
            ->whereKeyNot($persona->getKey());
    }
}
