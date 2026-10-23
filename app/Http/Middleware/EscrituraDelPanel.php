<?php

namespace App\Http\Middleware;

use App\Models\Asistencia;
use App\Models\ColorVehiculo;
use App\Models\Contrato;
use App\Models\Feriado;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\TipoVehiculo;
use App\Models\Turno;
use App\Models\User;
use App\Models\Vacacion;
use App\Models\Vehiculo;
use App\Models\Zona;
use App\Support\Bloqueo;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Serializa la decisión y su escritura, incluida la validación del controlador. */
class EscrituraDelPanel
{
    private const RECURSOS = [
        'colores' => [ColorVehiculo::class, 'color'],
        'marcas' => [Marca::class, 'marca'],
        'modelos' => [Modelo::class, 'modelo'],
        'tiposvehiculo' => [TipoVehiculo::class, 'tipo'],
        'vehiculos' => [Vehiculo::class, 'vehiculo'],
        'tipospersonal' => [TipoPersonal::class, 'tipo'],
        'personal' => [Personal::class, 'persona'],
        'contratos' => [Contrato::class, 'contrato'],
        'asistencias' => [Asistencia::class, 'asistencia'],
        'vacaciones' => [Vacacion::class, 'vacacion'],
        'usuarios' => [User::class, 'usuario'],
        'turnos' => [Turno::class, 'turno'],
        'zonas' => [Zona::class, 'zona'],
        'feriados' => [Feriado::class, 'feriado'],
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe() || $request->routeIs('admin.zonas.verificar')) {
            return $next($request);
        }

        $recurso = explode('.', $request->route()->getName())[1] ?? '';
        if ($recurso === 'contratos') {
            Contrato::cerrarVencidos();
        }
        [$clase, $parametro] = self::RECURSOS[$recurso] ?? [null, null];
        $id = $parametro ? $request->route($parametro) : null;
        $id = $id instanceof Model ? $id->getKey() : $id;
        $registro = $id && $clase ? $clase::findOrFail($id) : null;
        $idPersona = $registro instanceof Personal ? $registro->id : ($registro?->user_id ?? $request->input('user_id'));
        if ($request->routeIs('admin.asistencias.marcar') && is_string($request->input('dni'))) {
            // Identificar antes de abrir la transacción evita fijar un snapshot anterior al bloqueo en MySQL.
            $idPersona = Personal::where('dni', preg_replace('/\D/', '', $request->input('dni')))->value('id');
        }

        return DB::transaction(function () use ($request, $next, $recurso, $clase, $id, $registro, $idPersona) {
            if (in_array($recurso, ['asistencias', 'turnos'], true)) {
                Bloqueo::tomar('turnos');
            }
            if (in_array($recurso, ['zonas', 'usuarios'], true)) {
                Bloqueo::tomar($recurso === 'usuarios' ? 'administradores' : 'zonas');
            }
            $actor = User::whereKey($request->user()?->id)->lockForUpdate()->first();
            abort_unless($actor && $actor->status && $actor->usertype_id === null, 403);
            Auth::setUser($actor);

            if (in_array($recurso, ['personal', 'contratos', 'vacaciones', 'asistencias'], true) && is_scalar($idPersona) && ctype_digit((string) $idPersona)) {
                Personal::whereKey($idPersona)->lockForUpdate()->first();
            }
            if ($registro) {
                $registro = $clase::whereKey($id)->lockForUpdate()->firstOrFail();
                $request->validate(['version' => ['required', 'integer', 'min:1']]);
                abort_if((int) $request->input('version') !== (int) $registro->version, 409, 'El registro cambió. Recargue antes de guardar.');
            }

            $respuesta = $next($request);
            // Un rechazo no puede confirmar efectos laterales del controlador.
            if ($respuesta->getStatusCode() >= 400) {
                throw new HttpResponseException($respuesta);
            }

            return $respuesta;
        });
    }
}
