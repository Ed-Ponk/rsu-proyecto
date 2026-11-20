<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un permiso concedido a un usuario del sistema (código `recurso.accion`). */
class PermisoDeUsuario extends Model
{
    protected $table = 'user_permissions';

    protected $fillable = ['user_id', 'permission'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
