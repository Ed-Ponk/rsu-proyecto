<?php

namespace App\Support;

use Illuminate\Database\QueryException;

/** Traduce los errores de la base de datos que un usuario puede provocar sin querer (dos personas guardando lo mismo a la vez). */
class MensajesDeBase
{
    /** Mensaje según el índice único que se violó (el nombre del índice viene en el texto del error). */
    public static function paraDuplicado(string $error): string
    {
        $porIndice = [
            'plate' => 'Ya existe un vehículo con esa placa: otra persona lo acaba de registrar. Actualice la lista.',
            'vehicles_code' => 'Ya existe un vehículo con ese código: otra persona lo acaba de registrar. Actualice la lista.',
            'users_dni' => 'Ya hay personal registrado con ese DNI: otra persona lo acaba de registrar. Actualice la lista.',
            'users_email' => 'Ya hay una cuenta con ese correo: otra persona lo acaba de registrar. Actualice la lista.',
            'users_license' => 'Ya hay personal registrado con esa licencia: otra persona lo acaba de registrar.',
            'holidays_date' => 'Ya hay un feriado en esa fecha: otra persona lo acaba de registrar. Actualice la lista.',
            'un_solo_activo' => 'Esa persona ya tiene un contrato activo: otra persona lo acaba de registrar. Actualice la lista.',
            'brands_name' => 'Ya existe una marca con ese nombre: otra persona la acaba de registrar. Actualice la lista.',
            'vehiclecolors' => 'Ya existe un color con ese nombre o código: otra persona lo acaba de registrar. Actualice la lista.',
            'vehicletypes' => 'Ya existe un tipo de vehículo con ese nombre: otra persona lo acaba de registrar. Actualice la lista.',
            'brandmodels' => 'Esa marca ya tiene un modelo con ese nombre o código: otra persona lo acaba de registrar.',
            'schedules_name' => 'Ya existe un turno con ese nombre: otra persona lo acaba de registrar.',
            'zones_name' => 'Ya existe una zona con ese nombre: otra persona la acaba de registrar.',
            'staffgroups_name' => 'Ya existe un grupo con ese nombre: otra persona lo acaba de registrar.',
            'attendances' => 'Esa asistencia ya fue registrada: otra persona lo acaba de hacer. Actualice la lista.',
            'programm' => 'Otra persona acaba de programar a esa persona o vehículo en la misma fecha y turno. Vuelva a validar la disponibilidad.',
        ];

        foreach ($porIndice as $indice => $mensaje) {
            if (str_contains($error, $indice)) {
                return $mensaje;
            }
        }

        return 'Ese registro ya existe: otra persona lo acaba de guardar. Actualice la lista y revíselo.';
    }

    /** ¿Es un interbloqueo (deadlock) o un tiempo de espera de bloqueo agotado? */
    public static function esInterbloqueo(QueryException $e): bool
    {
        return in_array((string) $e->getCode(), ['40001', '40P01'], true) || str_contains($e->getMessage(), 'Deadlock found') || str_contains($e->getMessage(), 'Lock wait timeout');
    }
}
