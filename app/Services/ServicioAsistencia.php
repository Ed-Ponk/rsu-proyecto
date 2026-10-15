<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Personal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioAsistencia
{
    /** La primera marcación del día es entrada, la siguiente salida, y se van alternando. */
    public function siguienteTipo(int $idPersonal, string $dia, ?int $ignorarId = null): string
    {
        $hechas = Asistencia::where('user_id', $idPersonal)
            ->whereDate('date', $dia)
            ->where('status', Asistencia::PRESENTE)
            ->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))
            ->count();

        return $hechas % 2 === 0 ? Asistencia::ENTRADA : Asistencia::SALIDA;
    }

    /** Datos para precargar el formulario: ficha de la persona, marcaciones del día, tipo y turno sugeridos. */
    public function vistaPrevia(Personal $persona, string $dia, string $hora, ?int $ignorarId = null): array
    {
        $turno = ResolverTurno::paraHora($hora);

        $marcaciones = Asistencia::where('user_id', $persona->id)
            ->whereDate('date', $dia)
            ->orderBy('time')->orderBy('id')
            ->get()
            ->map(fn (Asistencia $a) => [
                'hora' => substr($a->time, 0, 5),
                'tipo' => $a->type,
                'estado' => $a->status,
            ]);

        return [
            'persona' => [
                'nombre' => $persona->nombre_completo,
                'dni' => $persona->dni,
                'correo' => $persona->email,
                'telefono' => $persona->phone,
                'funcion' => $persona->tipo?->name,
                'tiene_contrato' => $persona->contratos()->vigente(Carbon::parse($dia))->exists(),
            ],
            'tipo' => $this->siguienteTipo($persona->id, $dia, $ignorarId),
            'turno' => $turno?->name,
            'turno_etiqueta' => $turno?->etiqueta,
            'marcaciones' => $marcaciones,
        ];
    }

    /**
     * Registra una asistencia o una ausencia aplicando todas las reglas.
     *
     * @throws ValidationException
     */
    public function registrar(
        Personal $persona,
        Carbon $momento,
        string $estado = Asistencia::PRESENTE,
        ?string $notas = null,
        string $origen = 'admin',
        ?int $registradoPor = null,
        string $campoError = 'user_id',
    ): Asistencia {
        $this->verificarPuedeMarcar($persona, $momento, $campoError);

        return DB::transaction(function () use ($persona, $momento, $estado, $notas, $origen, $registradoPor, $campoError) {
            $dia = $momento->toDateString();
            $esAusencia = $estado === Asistencia::AUSENTE;

            $this->verificarCoherenciaDelDia($persona, $dia, $esAusencia, $campoError);

            return Asistencia::create([
                'user_id' => $persona->id,
                'date' => $dia,
                'time' => $momento->format('H:i:s'),
                'schedule_id' => ResolverTurno::paraHora($momento)?->id,
                'type' => $esAusencia ? null : $this->siguienteTipo($persona->id, $dia),
                'status' => $estado,
                'source' => $origen,
                'notes' => $notas,
                'registered_by' => $registradoPor,
            ]);
        });
    }

    /** Solo marca quien está activo, tiene contrato vigente y no intenta registrar el futuro. */
    public function verificarPuedeMarcar(Personal $persona, Carbon $momento, string $campoError = 'user_id'): void
    {
        $problema = match (true) {
            ! $persona->status => 'La persona está inactiva y no puede registrar asistencia.',
            $momento->greaterThan(now()->addMinute()) => 'No se puede registrar asistencia en una fecha u hora futura.',
            ! $persona->contratos()->vigente($momento->copy()->startOfDay())->exists() => 'La persona no tiene un contrato vigente en esa fecha.',
            default => null,
        };

        if ($problema) {
            throw ValidationException::withMessages([$campoError => $problema]);
        }
    }

    /** Un mismo día no puede tener ausencia y asistencia a la vez, ni dos ausencias. */
    private function verificarCoherenciaDelDia(Personal $persona, string $dia, bool $esAusencia, string $campoError): void
    {
        $delDia = Asistencia::where('user_id', $persona->id)->whereDate('date', $dia);
        $hayPresencia = (clone $delDia)->where('status', Asistencia::PRESENTE)->exists();
        $hayAusencia = (clone $delDia)->where('status', Asistencia::AUSENTE)->exists();

        $problema = match (true) {
            $esAusencia && $hayPresencia => 'La persona ya tiene marcaciones ese día; no puede registrarse como ausente.',
            $esAusencia && $hayAusencia => 'La persona ya tiene una ausencia registrada ese día.',
            ! $esAusencia && $hayAusencia => 'Ese día figura como ausente. Edite o elimine la ausencia antes de registrar asistencia.',
            default => null,
        };

        if ($problema) {
            throw ValidationException::withMessages([$campoError => $problema]);
        }
    }
}
