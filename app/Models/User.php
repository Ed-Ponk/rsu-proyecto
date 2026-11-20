<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /** El personal (con usertype_id) no inicia sesión: no se le envía el enlace de recuperación de contraseña. */
    public function sendPasswordResetNotification($token): void
    {
        if ($this->usertype_id !== null) {
            return;
        }

        parent::sendPasswordResetNotification($token);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birthdate' => 'date',
            'password' => 'hashed',
        ];
    }

    public const ADMINISTRADOR = 'administrador';

    public const OPERADOR = 'operador';

    /** Permisos concedidos, leídos una sola vez por petición. */
    private ?array $permisosEnMemoria = null;

    public function permisos(): HasMany
    {
        return $this->hasMany(PermisoDeUsuario::class, 'user_id');
    }

    /** Los administradores tienen acceso total; el personal operativo (con usertype_id) no tiene acceso al panel. */
    public function esAdministrador(): bool
    {
        // Una cuenta recién creada en memoria aún no trae la columna: su valor por defecto en la base es «administrador».
        return $this->usertype_id === null && ($this->role ?? self::ADMINISTRADOR) === self::ADMINISTRADOR;
    }

    /** @return list<string> */
    public function permisosConcedidos(): array
    {
        if ($this->usertype_id !== null) {
            return [];
        }

        return $this->permisosEnMemoria ??= $this->permisos()->pluck('permission')->all();
    }

    public function tienePermiso(string $codigo): bool
    {
        return $this->esAdministrador() || in_array($codigo, $this->permisosConcedidos(), true);
    }

    /** @param  list<string>  $codigos */
    public function tieneAlgunPermiso(array $codigos): bool
    {
        foreach ($codigos as $codigo) {
            if ($this->tienePermiso($codigo)) {
                return true;
            }
        }

        return false;
    }

    /** Para las pantallas: «*» si tiene acceso total, o la lista de códigos separados por coma (oculta los botones sin permiso). */
    public function permisosParaVista(): string
    {
        return $this->esAdministrador() ? '*' : implode(',', $this->permisosConcedidos());
    }

    public function olvidarPermisos(): void
    {
        $this->permisosEnMemoria = null;
    }

    /** Las fotos subidas desde el panel se guardan en /uploads (ruta pública); si no hay foto, iniciales con ui-avatars. */
    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $ruta = $this->profile_photo_path;

            if (filled($ruta)) {
                return preg_match('#^(https?://|/)#', $ruta) ? $ruta : asset($ruta);
            }

            $nombre = trim(($this->name ?? '').' '.($this->last_name ?? ''));
            $nombre = trim(implode(' ', array_map(fn ($parte) => mb_substr($parte, 0, 1), explode(' ', $nombre))));

            return 'https://ui-avatars.com/api/?name='.urlencode($nombre).'&color=7F9CF5&background=EBF4FF';
        });
    }

    public function adminlte_image()
    {
        return $this->profile_photo_url;
    }

    public function adminlte_desc()
    {
        return $this->esAdministrador() ? 'Administrador del sistema' : 'Operador del sistema';
    }
}
