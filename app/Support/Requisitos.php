<?php

namespace App\Support;

use App\Models\ColorVehiculo;
use App\Models\Contrato;
use App\Models\GrupoPersonal;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use App\Models\Zona;

/**
 * Qué debe existir antes de abrir cada formulario. Quien usa el sistema por primera vez no sabe en qué orden registrar las cosas:
 * en vez de dejarlo frente a buscadores vacíos, se le dice qué falta y dónde crearlo.
 *
 * @phpstan-type Falta array{texto: string, ruta: string, enlace: string}
 */
class Requisitos
{
    private static function falta(bool $existe, string $texto, string $ruta, string $enlace): array
    {
        return $existe ? [] : [['texto' => $texto, 'ruta' => route($ruta), 'enlace' => $enlace]];
    }

    public static function paraVehiculo(): array
    {
        return [
            ...self::falta(Marca::exists(), 'Al menos una marca', 'admin.marcas.index', 'Ir a Marcas'),
            ...self::falta(Modelo::exists(), 'Al menos un modelo de esa marca', 'admin.modelos.index', 'Ir a Modelos'),
            ...self::falta(TipoVehiculo::exists(), 'Al menos un tipo de vehículo', 'admin.tiposvehiculo.index', 'Ir a Tipos de vehículo'),
            ...self::falta(ColorVehiculo::exists(), 'Al menos un color', 'admin.colores.index', 'Ir a Colores'),
        ];
    }

    public static function paraContrato(): array
    {
        return self::falta(Personal::activos()->exists(), 'Personal activo al que asignarle el contrato', 'admin.personal.index', 'Ir a Personal');
    }

    public static function paraVacaciones(): array
    {
        $conDerecho = Personal::activos()->whereHas('contratos', fn ($c) => $c->vigente()->whereIn('type', [Contrato::NOMBRADO, Contrato::PERMANENTE]))->exists();

        return self::falta($conDerecho, 'Personal con contrato nombrado o permanente vigente (solo ellos pueden pedir vacaciones)', 'admin.contratos.index', 'Ir a Contratos');
    }

    public static function paraAsistencia(): array
    {
        $conContrato = Personal::activos()->whereHas('contratos', fn ($c) => $c->vigente())->exists();

        return self::falta($conContrato, 'Personal con contrato vigente (sin contrato no se puede registrar asistencia)', 'admin.contratos.index', 'Ir a Contratos');
    }

    public static function paraGrupo(): array
    {
        $conductor = TipoPersonal::conductor()?->id;
        $ayudante = TipoPersonal::ayudante()?->id;

        return [
            ...self::falta(Zona::where('status', true)->exists(), 'Al menos una zona activa (con su perímetro en el mapa)', 'admin.zonas.index', 'Ir a Zonas'),
            ...self::falta(Vehiculo::where('status', true)->exists(), 'Al menos un vehículo activo', 'admin.vehiculos.index', 'Ir a Vehículos'),
            ...self::falta(Personal::activos()->where('users.usertype_id', $conductor)->exists(), 'Al menos un conductor activo', 'admin.personal.index', 'Ir a Personal'),
            ...self::falta(Personal::activos()->where('users.usertype_id', $ayudante)->exists(), 'Al menos un ayudante activo', 'admin.personal.index', 'Ir a Personal'),
        ];
    }

    public static function paraProgramacion(): array
    {
        return self::falta(GrupoPersonal::where('status', true)->exists(), 'Al menos un grupo de personal activo (es la plantilla de la programación)', 'admin.grupos.index', 'Ir a Grupos de personal');
    }
}
