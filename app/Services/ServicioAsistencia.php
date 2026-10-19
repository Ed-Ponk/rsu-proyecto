<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Personal;
use App\Models\Turno;
use App\Models\Vacacion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioAsistencia
{
    /**
     * Decide a qué turno pertenece una marcación y si es entrada o salida. Regla: una entrada y una salida por turno.
     *
     *  1. Si la persona tiene una entrada abierta (sin salida) y aún no pasó el fin de ese turno + la gracia,
     *     la marcación es la SALIDA de ese turno (aunque el reloj ya esté en el turno siguiente).
     *  2. Si no, es una ENTRADA: del turno que empieza en unos minutos (llegó temprano) o del turno en curso.
     *  3. Si ese turno ya tiene entrada y salida, se rechaza.
     *
     * Devuelve ['turno', 'jornada', 'tipo', 'tardanza', 'problema'] (problema = null si se puede registrar).
     */
    public function resolverMarcacion(Personal $persona, Carbon $momento, ?int $ignorarId = null): array
    {
        // 1. ¿Hay una entrada abierta anterior a este momento?
        $ultima = Asistencia::with('turno')
            ->where('user_id', $persona->id)
            ->where('status', Asistencia::PRESENTE)
            ->whereNotNull('schedule_id')->whereNotNull('shift_date')
            ->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))
            ->where(fn ($q) => $q->where('date', '<', $momento->toDateString())
                ->orWhere(fn ($h) => $h->where('date', $momento->toDateString())->where('time', '<=', $momento->format('H:i:s'))))
            ->orderByDesc('date')->orderByDesc('time')->orderByDesc('id')
            ->first();

        if ($ultima?->type === Asistencia::ENTRADA && $ultima->turno) {
            $jornada = $ultima->shift_date->toDateString();
            $limite = $ultima->turno->finDe($jornada)->addMinutes((int) config('proyecto.asistencia.minutos_gracia_salida', 180));

            if ($momento->lte($limite) && $this->contarDelTurno($persona, $ultima->turno, $jornada, $ignorarId) < 2) {
                return $this->resultado($ultima->turno, $jornada, Asistencia::SALIDA);
            }
        }

        // 2. Entrada: turno que está por empezar (llegó temprano) o turno en curso.
        [$turno, $jornada] = $this->turnoParaEntrada($momento);

        if (! $turno) {
            return $this->resultado(null, null, null, problema: 'No hay un turno configurado para esa hora.');
        }

        $hechas = $this->contarDelTurno($persona, $turno, $jornada, $ignorarId);

        // 3. Turno completo.
        if ($hechas >= 2) {
            return $this->resultado($turno, $jornada, null, problema: "Ya registró su entrada y salida del turno {$turno->name}.");
        }

        if ($hechas === 1) {
            return $this->resultado($turno, $jornada, Asistencia::SALIDA);
        }

        return $this->resultado($turno, $jornada, Asistencia::ENTRADA, $turno->minutosDeTardanza($jornada, $momento));
    }

    /** @return array{0: ?Turno, 1: ?string} turno y día en que empieza */
    private function turnoParaEntrada(Carbon $momento): array
    {
        $anticipacion = (int) config('proyecto.asistencia.minutos_anticipacion_entrada', 30);

        // Un turno que empieza dentro de los próximos minutos tiene prioridad: la persona llegó temprano.
        foreach (ResolverTurno::todos() as $turno) {
            foreach ([$momento->toDateString(), $momento->copy()->addDay()->toDateString()] as $dia) {
                $faltan = $momento->diffInMinutes($turno->inicioDe($dia), false);

                if ($faltan > 0 && $faltan <= $anticipacion) {
                    return [$turno, $dia];
                }
            }
        }

        $turno = ResolverTurno::paraHora($momento);

        return [$turno, $turno?->jornadaPara($momento)];
    }

    private function contarDelTurno(Personal $persona, Turno $turno, string $jornada, ?int $ignorarId): int
    {
        return Asistencia::delTurno($persona->id, $turno->id, $jornada)
            ->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))
            ->count();
    }

    private function resultado(?Turno $turno, ?string $jornada, ?string $tipo, ?int $tardanza = null, ?string $problema = null): array
    {
        return compact('turno', 'jornada', 'tipo', 'tardanza', 'problema');
    }

    /** Datos para precargar el formulario: ficha de la persona, marcaciones del día, tipo, turno y tardanza previstos. */
    public function vistaPrevia(Personal $persona, string $dia, string $hora, ?int $ignorarId = null): array
    {
        $prevista = $this->resolverMarcacion($persona, Carbon::parse("{$dia} {$hora}"), $ignorarId);
        $turno = $prevista['turno'];
        $fechaDeVigencia = $this->fechaDeVigencia($prevista['tipo'], $prevista['jornada'], Carbon::parse($dia));

        $marcaciones = Asistencia::where('user_id', $persona->id)
            ->where('date', $dia)
            ->orderBy('time')->orderBy('id')
            ->get()
            ->map(fn (Asistencia $a) => [
                'hora' => substr($a->time, 0, 5),
                'tipo' => $a->type,
                'estado' => $a->status,
                'tardanza' => $a->late_minutes,
            ]);

        return [
            'persona' => [
                'nombre' => $persona->nombre_completo,
                'dni' => $persona->dni,
                'correo' => $persona->email,
                'telefono' => $persona->phone,
                'funcion' => $persona->tipo?->name,
                'tiene_contrato' => $persona->contratos()->vigente($fechaDeVigencia)->exists(),
                'de_vacaciones' => Vacacion::estaDeVacaciones($persona->id, $fechaDeVigencia),
            ],
            'tipo' => $prevista['tipo'],
            'tardanza' => $prevista['tardanza'],
            'problema' => $prevista['problema'],
            'turno' => $turno?->name,
            'turno_etiqueta' => $turno?->etiqueta,
            'marcaciones' => $marcaciones,
        ];
    }

    /**
     * Registra una asistencia o una ausencia aplicando todas las reglas.
     *
     * Toda la operación ocurre dentro de una transacción que primero BLOQUEA la fila de la persona: si dos marcaciones
     * de la misma persona llegan a la vez (doble toque, dos quioscos) se atienden una detrás de otra y la segunda ya
     * ve la primera. Sin ese bloqueo las dos leían "no hay marcaciones" y creaban dos entradas.
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
        $this->verificarEstadoYMomento($persona, $momento, $campoError);

        try {
            return DB::transaction(function () use ($persona, $momento, $estado, $notas, $origen, $registradoPor, $campoError) {
                Personal::whereKey($persona->id)->lockForUpdate()->first();

                $dia = $momento->toDateString();
                $esAusencia = $estado === Asistencia::AUSENTE;

                $this->verificarCoherenciaDelDia($persona, $dia, $esAusencia, $campoError);

                // Una ausencia no tiene entrada/salida: se asocia al turno de la hora y al mismo día.
                if ($esAusencia) {
                    $this->verificarVigencia($persona, $momento->copy()->startOfDay(), $campoError);
                    $turno = ResolverTurno::paraHora($momento);

                    return Asistencia::create([
                        'user_id' => $persona->id,
                        'date' => $dia,
                        'time' => $momento->format('H:i:s'),
                        'schedule_id' => $turno?->id,
                        'shift_date' => $dia,
                        'type' => null,
                        'late_minutes' => null,
                        'status' => $estado,
                        'source' => $origen,
                        'notes' => $notas,
                        'registered_by' => $registradoPor,
                    ]);
                }

                $marcacion = $this->resolverMarcacion($persona, $momento);

                if ($marcacion['problema']) {
                    throw ValidationException::withMessages([$campoError => $marcacion['problema']]);
                }

                // Contrato y vacaciones se miden el día en que EMPEZÓ el turno: la salida de la madrugada pertenece a la
                // jornada anterior, aunque el contrato haya vencido o las vacaciones hayan empezado a medianoche.
                $this->verificarVigencia($persona, $this->fechaDeVigencia($marcacion['tipo'], $marcacion['jornada'], $momento->copy()->startOfDay()), $campoError);

                // Se crea sin tipo y renumerarTurno lo asigna (y calcula la tardanza) según el orden de las marcaciones.
                $asistencia = Asistencia::create([
                    'user_id' => $persona->id,
                    'date' => $dia,
                    'time' => $momento->format('H:i:s'),
                    'schedule_id' => $marcacion['turno']->id,
                    'shift_date' => $marcacion['jornada'],
                    'type' => null,
                    'late_minutes' => null,
                    'status' => $estado,
                    'source' => $origen,
                    'notes' => $notas,
                    'registered_by' => $registradoPor,
                ]);

                Asistencia::renumerarTurno($persona->id, $marcacion['turno']->id, $marcacion['jornada']);

                return $asistencia->fresh();
            });
        } catch (UniqueConstraintViolationException) {
            // Última defensa: el índice único (usuario, turno, jornada, tipo) impidió una marcación repetida.
            throw ValidationException::withMessages([$campoError => 'Esa marcación ya fue registrada. Actualice la pantalla.']);
        }
    }

    /** Segundos que faltan para que la persona pueda volver a marcar con su DNI (0 si ya puede). */
    public function segundosParaVolverAMarcar(Personal $persona): int
    {
        $ultima = Asistencia::where('user_id', $persona->id)
            ->where('status', Asistencia::PRESENTE)
            ->orderByDesc('date')->orderByDesc('time')->orderByDesc('id')
            ->first();

        if (! $ultima) {
            return 0;
        }

        $transcurridos = Carbon::parse($ultima->date->toDateString().' '.$ultima->time)->diffInSeconds(now(), false);
        $espera = (int) config('proyecto.asistencia.segundos_entre_marcaciones', 60);

        return $transcurridos >= 0 && $transcurridos < $espera ? (int) ceil($espera - $transcurridos) : 0;
    }

    /** Solo marca quien está activo, tiene contrato vigente y no intenta registrar el futuro. */
    public function verificarPuedeMarcar(Personal $persona, Carbon $momento, string $campoError = 'user_id'): void
    {
        $this->verificarEstadoYMomento($persona, $momento, $campoError);
        $this->verificarVigencia($persona, $momento->copy()->startOfDay(), $campoError);
    }

    /** La persona debe estar activa y la marcación no puede ser del futuro. */
    public function verificarEstadoYMomento(Personal $persona, Carbon $momento, string $campoError = 'user_id'): void
    {
        $problema = match (true) {
            ! $persona->status => 'La persona está inactiva y no puede registrar asistencia.',
            $momento->greaterThan(now()->addMinute()) => 'No se puede registrar asistencia en una fecha u hora futura.',
            default => null,
        };

        if ($problema) {
            throw ValidationException::withMessages([$campoError => $problema]);
        }
    }

    /** Contrato vigente y sin vacaciones aprobadas en la fecha indicada. */
    public function verificarVigencia(Personal $persona, Carbon $fecha, string $campoError = 'user_id'): void
    {
        $problema = match (true) {
            ! $persona->contratos()->vigente($fecha->copy()->startOfDay())->exists() => 'La persona no tiene un contrato vigente en esa fecha.',
            Vacacion::estaDeVacaciones($persona->id, $fecha) => 'La persona está de vacaciones aprobadas en esa fecha y no puede registrar asistencia.',
            default => null,
        };

        if ($problema) {
            throw ValidationException::withMessages([$campoError => $problema]);
        }
    }

    /** Fecha con la que se mide la vigencia: el día en que empezó el turno si es una SALIDA; si no, el día de la marcación. */
    public function fechaDeVigencia(?string $tipo, ?string $jornada, Carbon $diaDeLaMarcacion): Carbon
    {
        return $tipo === Asistencia::SALIDA && $jornada ? Carbon::parse($jornada)->startOfDay() : $diaDeLaMarcacion->copy()->startOfDay();
    }

    /** Un mismo día no puede tener ausencia y asistencia a la vez, ni dos ausencias. */
    private function verificarCoherenciaDelDia(Personal $persona, string $dia, bool $esAusencia, string $campoError): void
    {
        $delDia = Asistencia::where('user_id', $persona->id)->where('date', $dia);
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
