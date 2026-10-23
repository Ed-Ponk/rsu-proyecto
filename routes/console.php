<?php

use App\Models\Contrato;
use App\Models\User;
use App\Rules\ClaveCompatible;
use App\Support\Bloqueo;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('contratos:cerrar-vencidos', function () {
    $cerrados = Contrato::cerrarVencidos();
    $this->info("Contratos vencidos cerrados: {$cerrados}");
})->purpose('Desactiva los contratos temporales cuya fecha de término ya pasó');

Artisan::command('admin:cambiar-clave {correo : Correo del administrador}', function (string $correo) {
    $usuario = User::whereNull('usertype_id')->where('email', strtolower($correo))->first();

    if (! $usuario) {
        $this->error('No hay un administrador con ese correo.');

        return self::FAILURE;
    }

    $clave = $this->secret('Nueva contraseña (mínimo 8 caracteres con mayúsculas, minúsculas y números)');
    $reglas = ['password' => ['required', 'string', new ClaveCompatible, Password::defaults()]];

    $validador = Validator::make(['password' => $clave], $reglas);

    if ($validador->fails()) {
        $this->error($validador->errors()->first('password'));

        return self::FAILURE;
    }

    DB::transaction(function () use ($usuario, $clave) {
        Bloqueo::tomar('administradores');
        $actual = User::whereNull('usertype_id')->whereKey($usuario->id)->lockForUpdate()->firstOrFail();
        $actual->forceFill(['password' => $clave, 'status' => true])->save();
    });
    $this->info('Contraseña actualizada. La cuenta quedó activa.');
})->purpose('Cambia la contraseña de un administrador (para recuperar el acceso sin correo)');

// Cada día, un poco después de medianoche. Requiere el cron de Laravel: * * * * * php artisan schedule:run
Schedule::command('contratos:cerrar-vencidos')->dailyAt('00:05')->withoutOverlapping();
