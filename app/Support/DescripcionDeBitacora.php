<?php

namespace App\Support;

use App\Models\Asistencia;
use App\Models\Bitacora;
use App\Models\Contrato;
use App\Models\GrupoPersonal;
use App\Models\Programacion;
use App\Models\Vacacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Traduce la bitácora a palabras que entiende cualquier usuario: «Turno: Mañana (06:00 - 14:00) → Tarde (14:00 - 22:00)»
 * en lugar de «shift_id: 1 → 2». Los nombres se buscan una sola vez por pantalla (caché en memoria).
 */
class DescripcionDeBitacora
{
    /** Nombre de cada módulo registrado y la tabla donde vive (para mostrar el nombre actual del registro). */
    public const MODULOS = [
        'Asistencia' => ['Asistencia', 'attendances'],
        'Contrato' => ['Contrato', 'contracts'],
        'Feriado' => ['Feriado', 'holidays'],
        'GrupoPersonal' => ['Grupo de personal', 'staffgroups'],
        'Motivo' => ['Motivo de cambio', 'change_reasons'],
        'Personal' => ['Personal', 'users'],
        'Programacion' => ['Programación', 'programmings'],
        'Turno' => ['Turno', 'schedules'],
        'Vacacion' => ['Vacaciones', 'vacations'],
        'Zona' => ['Zona', 'zones'],
    ];

    private const CAMPOS = [
        'name' => 'Nombre', 'last_name' => 'Apellidos', 'dni' => 'DNI', 'birthdate' => 'Fecha de nacimiento', 'phone' => 'Teléfono',
        'email' => 'Correo', 'address' => 'Dirección', 'license' => 'Licencia', 'profile_photo_path' => 'Foto', 'usertype_id' => 'Función',
        'status' => 'Estado', 'description' => 'Descripción', 'notes' => 'Observaciones',
        'date' => 'Fecha', 'start_date' => 'Fecha de inicio', 'end_date' => 'Fecha de fin', 'time' => 'Hora',
        'time_start' => 'Hora de inicio', 'time_end' => 'Hora de fin', 'type' => 'Tipo', 'salary' => 'Sueldo',
        'probation_months' => 'Periodo de prueba', 'is_active' => 'Contrato activo', 'days' => 'Días',
        'user_id' => 'Persona', 'registered_by' => 'Registrado por', 'resolved_by' => 'Resuelto por', 'resolved_at' => 'Resuelto el',
        'created_by' => 'Creado por', 'schedule_id' => 'Turno', 'shift_id' => 'Turno', 'shift_date' => 'Jornada',
        'shift_start' => 'Hora de inicio del turno', 'late_minutes' => 'Tardanza', 'source' => 'Origen',
        'zone_id' => 'Zona', 'group_id' => 'Grupo', 'vehicle_id' => 'Vehículo', 'driver_id' => 'Conductor', 'ayudantes' => 'Ayudantes',
        'weekdays' => 'Días de trabajo', 'department' => 'Departamento', 'province' => 'Provincia', 'district' => 'Distrito',
        'average_waste' => 'Residuos promedio',
    ];

    /** Campos que resumen un registro al crearlo o eliminarlo (el resto queda en el detalle de cada módulo). */
    private const RESUMEN = [
        'Asistencia' => ['schedule_id', 'time', 'type', 'status', 'late_minutes', 'source', 'notes'],
        'Contrato' => ['type', 'start_date', 'end_date', 'salary', 'is_active'],
        'Feriado' => ['date', 'description', 'status'],
        'GrupoPersonal' => ['shift_id', 'zone_id', 'vehicle_id', 'driver_id', 'weekdays', 'status'],
        'Motivo' => ['description'],
        'Personal' => ['dni', 'usertype_id', 'email', 'phone', 'status'],
        'Programacion' => ['date', 'shift_id', 'zone_id', 'vehicle_id', 'driver_id', 'status'],
        'Turno' => ['time_start', 'time_end', 'description'],
        'Vacacion' => ['start_date', 'end_date', 'days', 'status'],
        'Zona' => ['district', 'average_waste', 'status'],
    ];

    /** @var array<string, mixed> */
    private array $cache = [];

    public static function modulo(string $modelo): string
    {
        return self::MODULOS[$modelo][0] ?? $modelo;
    }

    /** Qué registro es, con su nombre: «Personal: Jorge Díaz Torres», «Programación: 06/01/2027 · Zona Sur · Tarde». */
    public function registro(Bitacora $b): string
    {
        $datos = [...($b->before ?? []), ...($b->after ?? []), ...$this->actual($b)];
        $nombre = match ($b->model) {
            'Personal' => trim(($datos['name'] ?? '').' '.($datos['last_name'] ?? '')),
            'Asistencia' => $this->persona($datos['user_id'] ?? null).$this->entre($this->fecha($datos['shift_date'] ?? $datos['date'] ?? null)),
            'Contrato', 'Vacacion' => $this->persona($datos['user_id'] ?? null),
            'Programacion' => collect([$this->fecha($datos['date'] ?? null), $this->zona($datos['zone_id'] ?? null), $this->turno($datos['shift_id'] ?? null, false)])
                ->filter()->implode(' · '),
            'Feriado' => ($datos['description'] ?? '').$this->entre($this->fecha($datos['date'] ?? null)),
            default => (string) ($datos['name'] ?? ''),
        };

        return self::modulo($b->model).': '.($nombre !== '' ? $nombre : 'N.º '.$b->model_id);
    }

    /** Lo que pasó, en palabras. Modificado: «Campo: antes → después»; creado o eliminado: sus datos principales. */
    public function detalle(Bitacora $b, int $largo = 300): string
    {
        if ($b->action === 'modificado') {
            $texto = implode('; ', $this->cambios($b));
        } else {
            $datos = ($b->action === 'eliminado' ? $b->before : $b->after) ?? [];
            $texto = collect(self::RESUMEN[$b->model] ?? array_keys($datos))
                ->filter(fn (string $campo) => array_key_exists($campo, $datos) && $datos[$campo] !== null && $datos[$campo] !== '')
                ->map(fn (string $campo) => self::campo($campo).': '.$this->valor($b->model, $campo, $datos[$campo]))
                ->implode('; ');
        }

        return mb_strimwidth($texto ?: 'Sin datos', 0, $largo, '…');
    }

    /** @return list<string> «Campo: antes → después» por cada campo modificado */
    public function cambios(Bitacora $b): array
    {
        $partes = [];

        foreach ($b->after ?? [] as $campo => $nuevo) {
            $antes = $this->valor($b->model, $campo, $b->before[$campo] ?? null);
            $despues = $this->valor($b->model, $campo, $nuevo);

            // «06:00:00» y «06:00» se guardan distinto pero son lo mismo para el usuario: no es un cambio.
            if ($antes !== $despues) {
                $partes[] = self::campo($campo).": {$antes} → {$despues}";
            }
        }

        return $partes;
    }

    public static function campo(string $campo): string
    {
        return self::CAMPOS[$campo] ?? ucfirst(str_replace('_', ' ', preg_replace('/_id$/', '', $campo)));
    }

    /** Valor tal como se ve en las pantallas del sistema. */
    public function valor(string $modelo, string $campo, mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return match ($campo) {
            'user_id', 'driver_id', 'registered_by', 'resolved_by', 'created_by' => $this->persona($valor) ?: 'Persona eliminada',
            'schedule_id', 'shift_id' => $this->turno($valor) ?: 'Turno eliminado',
            'zone_id' => $this->zona($valor) ?: 'Zona eliminada',
            'group_id' => $this->buscar('staffgroups', $valor)?->name ?? 'Grupo eliminado',
            'vehicle_id' => ($v = $this->buscar('vehicles', $valor)) ? "{$v->name} ({$v->plate})" : 'Vehículo eliminado',
            'usertype_id' => $this->buscar('usertypes', $valor)?->name ?? '—',
            'date', 'start_date', 'end_date', 'birthdate', 'shift_date' => $this->fecha($valor),
            'resolved_at' => Carbon::parse($valor)->format('d/m/Y H:i'),
            'time', 'time_start', 'time_end', 'shift_start' => substr((string) $valor, 0, 5),
            'status' => $this->estado($modelo, $valor),
            'type' => $modelo === 'Contrato' ? (Contrato::TIPOS[$valor] ?? (string) $valor) : ucfirst((string) $valor),
            'is_active' => $valor ? 'Sí' : 'No',
            'late_minutes' => (int) $valor > 0 ? Asistencia::textoDeDuracion((int) $valor) : 'Sin tardanza',
            'salary' => 'S/ '.number_format((float) $valor, 2),
            'probation_months' => $valor.' '.((int) $valor === 1 ? 'mes' : 'meses'),
            'days' => $valor.' '.((int) $valor === 1 ? 'día' : 'días'),
            'average_waste' => number_format((float) $valor, 2).' kg',
            'weekdays' => collect(is_array($valor) ? $valor : (json_decode((string) $valor, true) ?? []))
                ->map(fn ($dia) => GrupoPersonal::DIAS[(int) $dia] ?? $dia)->implode(', '),
            'source' => ['admin' => 'Registrada por el administrador', 'marcacion' => 'Marcación con DNI'][$valor] ?? (string) $valor,
            'profile_photo_path' => 'Foto cargada',
            default => is_bool($valor) ? ($valor ? 'Sí' : 'No') : (is_array($valor) ? implode(', ', $valor) : (string) $valor),
        };
    }

    private function estado(string $modelo, mixed $valor): string
    {
        return match ($modelo) {
            'Programacion' => Programacion::ESTADOS[$valor] ?? (string) $valor,
            'Vacacion' => Vacacion::ESTADOS[$valor] ?? (string) $valor,
            'Asistencia' => ucfirst((string) $valor),
            default => in_array($valor, [true, 1, '1', 'true'], true) ? 'Activo' : 'Inactivo',
        };
    }

    private function persona(mixed $id): string
    {
        $p = $this->buscar('users', $id);

        return $p ? trim($p->name.' '.($p->last_name ?? '')) : '';
    }

    private function turno(mixed $id, bool $conHoras = true): string
    {
        $t = $this->buscar('schedules', $id);

        if (! $t) {
            return '';
        }

        return $conHoras ? "{$t->name} (".substr($t->time_start, 0, 5).' - '.substr($t->time_end, 0, 5).')' : $t->name;
    }

    private function zona(mixed $id): string
    {
        return (string) ($this->buscar('zones', $id)?->name ?? '');
    }

    private function fecha(mixed $valor): string
    {
        return $valor ? Carbon::parse($valor)->format('d/m/Y') : '';
    }

    private function entre(string $texto): string
    {
        return $texto !== '' ? " ({$texto})" : '';
    }

    /** Datos actuales del registro (vacío si ya se eliminó). @return array<string, mixed> */
    private function actual(Bitacora $b): array
    {
        $tabla = self::MODULOS[$b->model][1] ?? null;

        return $tabla ? (array) ($this->buscar($tabla, $b->model_id) ?? []) : [];
    }

    private function buscar(string $tabla, mixed $id): ?object
    {
        if (! is_numeric($id)) {
            return null;
        }

        $clave = $tabla.'|'.$id;

        if (! array_key_exists($clave, $this->cache)) {
            $this->cache[$clave] = DB::table($tabla)->where('id', (int) $id)->first();
        }

        return $this->cache[$clave];
    }
}
