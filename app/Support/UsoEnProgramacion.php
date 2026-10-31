<?php

namespace App\Support;

use App\Models\GrupoPersonal;
use App\Models\Personal;
use App\Models\Programacion;
use App\Models\Vehiculo;
use App\Models\Zona;
use Illuminate\Validation\ValidationException;

/**
 * Qué grupos y programaciones dependen de un vehículo, zona, turno o persona.
 * Sirve para impedir borrar, desactivar o cambiar algo que está en uso (antes la base de datos respondía con un error 500).
 */
class UsoEnProgramacion
{
    /** "2 grupo(s) y 14 programación(es)" o null si no se usa en ninguno. */
    public static function frase(int $grupos, int $programaciones): ?string
    {
        $partes = array_filter([
            $grupos ? $grupos.' '.($grupos === 1 ? 'grupo' : 'grupos') : null,
            $programaciones ? $programaciones.' '.($programaciones === 1 ? 'programación' : 'programaciones') : null,
        ]);

        return $partes ? implode(' y ', $partes) : null;
    }

    public static function deVehiculo(int $id): ?string
    {
        return self::frase(GrupoPersonal::where('vehicle_id', $id)->count(), Programacion::where('vehicle_id', $id)->count());
    }

    public static function deZona(int $id): ?string
    {
        return self::frase(GrupoPersonal::where('zone_id', $id)->count(), Programacion::where('zone_id', $id)->count());
    }

    public static function deTurno(int $id): ?string
    {
        return self::frase(GrupoPersonal::where('shift_id', $id)->count(), Programacion::where('shift_id', $id)->count());
    }

    public static function dePersona(int $id): ?string
    {
        $grupos = GrupoPersonal::where('driver_id', $id)
            ->orWhereHas('ayudantes', fn ($q) => $q->where('users.id', $id))->count();

        return self::frase($grupos, Programacion::dePersona($id)->count());
    }

    /** Programaciones que todavía no se cumplieron (programadas desde hoy en adelante). */
    private static function pendientes()
    {
        return Programacion::pendientes()->where('date', '>=', today()->toDateString());
    }

    /** No se desactiva ni se reduce la capacidad de un vehículo que tiene programaciones pendientes o grupos que no cabrían. */
    public static function exigirVehiculoEditable(Vehiculo $vehiculo, array $nuevos): void
    {
        $pendientes = self::pendientes()->where('vehicle_id', $vehiculo->id)->count();

        if (array_key_exists('status', $nuevos) && ! $nuevos['status'] && $vehiculo->status && $pendientes > 0) {
            throw ValidationException::withMessages(['status' => "No se puede desactivar: tiene {$pendientes} programación(es) pendiente(s). Modifíquelas o elimínelas primero."]);
        }

        if (isset($nuevos['occupant_capacity'])) {
            $necesarias = max([
                0,
                ...GrupoPersonal::withCount('ayudantes')->where('vehicle_id', $vehiculo->id)->get()->map(fn ($g) => 1 + $g->ayudantes_count)->all(),
                ...self::pendientes()->where('vehicle_id', $vehiculo->id)->withCount('ayudantes')->get()->map(fn ($p) => 1 + $p->ayudantes_count)->all(),
            ]);

            if ((int) $nuevos['occupant_capacity'] < $necesarias) {
                throw ValidationException::withMessages(['occupant_capacity' => "Hay grupos o programaciones pendientes que usan este vehículo con {$necesarias} persona(s): no puede tener menos capacidad."]);
            }
        }
    }

    public static function exigirZonaEditable(Zona $zona, array $nuevos): void
    {
        $pendientes = self::pendientes()->where('zone_id', $zona->id)->count();

        if (array_key_exists('status', $nuevos) && ! $nuevos['status'] && $zona->status && $pendientes > 0) {
            throw ValidationException::withMessages(['status' => "No se puede desactivar: tiene {$pendientes} programación(es) pendiente(s). Modifíquelas o elimínelas primero."]);
        }
    }

    /** Una persona no cambia de función (conductor ↔ ayudante) mientras figure en un grupo o en programaciones pendientes. */
    public static function exigirPersonaEditable(Personal $persona, array $nuevos): void
    {
        if (! isset($nuevos['usertype_id']) || (int) $nuevos['usertype_id'] === (int) $persona->usertype_id) {
            return;
        }

        $enGrupos = GrupoPersonal::where('driver_id', $persona->id)->orWhereHas('ayudantes', fn ($q) => $q->where('users.id', $persona->id))->count();
        $pendientes = self::pendientes()->dePersona($persona->id)->count();

        if ($uso = self::frase($enGrupos, $pendientes)) {
            throw ValidationException::withMessages(['usertype_id' => "No se puede cambiar la función: figura en {$uso}. Quítela de ellos primero."]);
        }
    }
}
