<?php

namespace App\Services;

use App\Models\Feriado;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Carga de un solo clic los feriados oficiales del Perú de un año.
 * Primero consulta la API pública Nager.Date; si no hay internet o la API falla, usa el calendario propio
 * (fechas fijas + Semana Santa calculada), de modo que el botón siempre funciona.
 */
class CargadorFeriados
{
    private const API = 'https://date.nager.at/api/v3/PublicHolidays/%d/PE';

    /** Cómo se presenta cada origen al usuario. */
    public const FUENTES = [
        'api' => 'API pública Nager.Date (date.nager.at)',
        'local' => 'Calendario oficial del sistema (sin conexión)',
    ];

    /** Fechas fijas del calendario oficial: 'mes-día' => descripción. */
    private const FIJOS = [
        '01-01' => 'Año Nuevo',
        '05-01' => 'Día del Trabajo',
        '06-29' => 'Día de San Pedro y San Pablo',
        '07-23' => 'Día de la Fuerza Aérea del Perú',
        '07-28' => 'Fiestas Patrias',
        '07-29' => 'Fiestas Patrias',
        '08-06' => 'Batalla de Junín',
        '08-30' => 'Santa Rosa de Lima',
        '10-08' => 'Combate de Angamos',
        '11-01' => 'Día de Todos los Santos',
        '12-08' => 'Inmaculada Concepción',
        '12-09' => 'Batalla de Ayacucho',
        '12-25' => 'Navidad',
    ];

    /** @return array{creados:int, existentes:int, origen:string} */
    public function cargar(int $anio): array
    {
        [$feriados, $origen] = $this->obtener($anio);

        return $this->guardar($feriados) + ['origen' => $origen];
    }

    /**
     * Guarda un grupo de feriados (fecha => descripción) sin tocar los que ya existen.
     *
     * @param  array<string,string>  $feriados
     * @return array{creados:int, existentes:int}
     */
    public function guardar(array $feriados): array
    {
        $creados = 0;
        $existentes = 0;

        foreach ($feriados as $fecha => $descripcion) {
            // Respeta lo que ya existe (aunque el usuario lo haya editado o desactivado).
            $feriado = Feriado::firstOrCreate(
                ['date' => $fecha],
                ['description' => $descripcion, 'status' => true],
            );

            $feriado->wasRecentlyCreated ? $creados++ : $existentes++;
        }

        return ['creados' => $creados, 'existentes' => $existentes];
    }

    /** @return array{0: array<string,string>, 1: string} fecha => descripción, y de dónde salieron. */
    public function obtener(int $anio): array
    {
        try {
            $respuesta = Http::timeout(8)->acceptJson()->get(sprintf(self::API, $anio));

            if ($respuesta->successful() && is_array($respuesta->json()) && $respuesta->json() !== []) {
                $feriados = [];
                foreach ($respuesta->json() as $dia) {
                    $feriados[$dia['date']] = $dia['localName'] ?? $dia['name'];
                }

                return [$feriados, 'api'];
            }
        } catch (Throwable $e) {
            // Sin internet o bloqueado: se usa el calendario propio (pero se deja constancia).
            Log::warning('No se pudo consultar la API de feriados; se usa el calendario del sistema.', ['error' => $e->getMessage()]);
        }

        return [$this->calendarioPropio($anio), 'local'];
    }

    /** @return array<string,string> */
    public function calendarioPropio(int $anio): array
    {
        $feriados = [];

        foreach (self::FIJOS as $mesDia => $descripcion) {
            $feriados["{$anio}-{$mesDia}"] = $descripcion;
        }

        $pascua = $this->domingoDePascua($anio);
        $feriados[$pascua->copy()->subDays(3)->toDateString()] = 'Jueves Santo';
        $feriados[$pascua->copy()->subDays(2)->toDateString()] = 'Viernes Santo';
        $feriados[$pascua->toDateString()] = 'Domingo de Resurrección';

        ksort($feriados);

        return $feriados;
    }

    /** Algoritmo de Gauss/Meeus para el calendario gregoriano (no depende de la extensión "calendar" de PHP). */
    public function domingoDePascua(int $anio): Carbon
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($anio, $mes, $dia)->startOfDay();
    }
}
