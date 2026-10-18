<?php

namespace App\Services;

use App\Models\Zona;

/**
 * Reglas del perímetro de una zona. Recibe los vértices como [['latitude' => ..., 'longitude' => ...], ...] y
 * devuelve el mensaje de error o null si el polígono es válido.
 */
class ReglasZona
{
    public const MINIMO_PUNTOS = 3;

    public function revisarPerimetro(array $coordenadas, ?string $departamento = null): ?string
    {
        $puntos = array_values(array_map(fn ($c) => [(float) $c['latitude'], (float) $c['longitude']], $coordenadas));
        $n = count($puntos);

        if ($n < self::MINIMO_PUNTOS) {
            return 'El perímetro necesita al menos '.self::MINIMO_PUNTOS.' coordenadas.';
        }

        $limites = config('ubigeo.limites_peru');
        foreach ($puntos as $i => [$lat, $lng]) {
            if ($lat < $limites['lat_min'] || $lat > $limites['lat_max'] || $lng < $limites['lng_min'] || $lng > $limites['lng_max']) {
                return 'La coordenada '.($i + 1).' está fuera del territorio peruano.';
            }
        }

        // Las coordenadas deben caer dentro del departamento elegido (rectángulo con margen de config/ubigeo.php).
        $caja = $departamento ? config("ubigeo.limites_departamento.{$departamento}") : null;
        if ($caja) {
            foreach ($puntos as $i => [$lat, $lng]) {
                if ($lat < $caja['lat_min'] || $lat > $caja['lat_max'] || $lng < $caja['lng_min'] || $lng > $caja['lng_max']) {
                    return 'La coordenada '.($i + 1)." está fuera del departamento de {$departamento}.";
                }
            }
        }

        for ($i = 0; $i < $n; $i++) {
            if ($puntos[$i] === $puntos[($i + 1) % $n]) {
                return 'Hay dos coordenadas consecutivas repetidas (puntos '.($i + 1).' y '.((($i + 1) % $n) + 1).').';
            }
        }

        // Primero el cruce: un "moño" tiene área neta cero pero no está alineado.
        if ($this->seCruza($puntos)) {
            return 'El perímetro se cruza consigo mismo. Ordene las coordenadas siguiendo el contorno de la zona.';
        }

        if ($this->area($puntos) < 1e-9) {
            return 'Las coordenadas están alineadas: no forman una superficie.';
        }

        $area = self::areaKm2($puntos);
        $min = (float) config('proyecto.zonas.area_minima_km2');
        $max = (float) config('proyecto.zonas.area_maxima_km2');

        if ($area < $min) {
            return 'La zona es demasiado pequeña ('.number_format($area * 1_000_000, 0, '.', '').' m²). Revise las coordenadas.';
        }

        if ($area > $max) {
            return 'La zona es demasiado grande ('.number_format($area, 1).' km²; máximo '.$max.' km²). Divídala en varias zonas.';
        }

        return null;
    }

    /** Área aproximada en km² de un polígono de puntos [lat, lng] (proyección plana local, fórmula del "lazo"). */
    public static function areaKm2(array $puntos): float
    {
        $n = count($puntos);

        if ($n < 3) {
            return 0.0;
        }

        $latMedia = array_sum(array_column($puntos, 0)) / $n;
        $kmLat = 111.32;
        $kmLng = 111.32 * cos(deg2rad($latMedia));

        $suma = 0.0;
        for ($i = 0; $i < $n; $i++) {
            [$lat1, $lng1] = $puntos[$i];
            [$lat2, $lng2] = $puntos[($i + 1) % $n];
            $suma += ($lng1 * $kmLng) * ($lat2 * $kmLat) - ($lng2 * $kmLng) * ($lat1 * $kmLat);
        }

        return abs($suma) / 2;
    }

    /**
     * Busca otra zona cuya superficie se superponga con el perímetro dado (más de la tolerancia permitida).
     *
     * @return array{zona: Zona, porcentaje: int}|null
     */
    public function zonaInvadida(array $coordenadas, ?int $ignorarId = null): ?array
    {
        $nuevo = array_values(array_map(fn ($c) => [(float) $c['latitude'], (float) $c['longitude']], $coordenadas));
        $tolerancia = (float) config('proyecto.zonas.solape_maximo', 0.01);

        $otras = Zona::with('coordenadas')
            ->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))
            ->get();

        foreach ($otras as $otra) {
            $vertices = $otra->vertices();
            if (count($vertices) < self::MINIMO_PUNTOS) {
                continue;
            }

            $fraccion = $this->fraccionEnComun($nuevo, $vertices);

            if ($fraccion > $tolerancia) {
                return ['zona' => $otra, 'porcentaje' => max(1, (int) round($fraccion * 100))];
            }
        }

        return null;
    }

    /** Mensaje listo para mostrar cuando el perímetro invade otra zona, o null si no invade ninguna. */
    public function mensajeDeInvasion(array $coordenadas, ?int $ignorarId = null): ?string
    {
        $invasion = $this->zonaInvadida($coordenadas, $ignorarId);

        return $invasion
            ? "El perímetro invade la zona «{$invasion['zona']->name}» (se superpone ≈ {$invasion['porcentaje']} % de la más pequeña). Ajuste los puntos para que no se crucen."
            : null;
    }

    /**
     * Qué parte de la zona más pequeña está dentro de las dos. Se estima muestreando una cuadrícula sobre el
     * rectángulo que comparten ambas figuras (sin librerías de geometría y con precisión de sobra para este uso).
     */
    private function fraccionEnComun(array $a, array $b): float
    {
        $lat = fn (array $p) => array_column($p, 0);
        $lng = fn (array $p) => array_column($p, 1);

        $latMin = max(min($lat($a)), min($lat($b)));
        $latMax = min(max($lat($a)), max($lat($b)));
        $lngMin = max(min($lng($a)), min($lng($b)));
        $lngMax = min(max($lng($a)), max($lng($b)));

        if ($latMin >= $latMax || $lngMin >= $lngMax) {
            return 0.0;   // ni siquiera sus rectángulos se tocan
        }

        $pasos = 120;
        $dentro = 0;
        for ($i = 0; $i < $pasos; $i++) {
            $y = $latMin + ($latMax - $latMin) * (($i + 0.5) / $pasos);
            for ($j = 0; $j < $pasos; $j++) {
                $x = $lngMin + ($lngMax - $lngMin) * (($j + 0.5) / $pasos);
                if ($this->contiene($a, $y, $x) && $this->contiene($b, $y, $x)) {
                    $dentro++;
                }
            }
        }

        $comun = ($dentro / ($pasos * $pasos)) * ($latMax - $latMin) * ($lngMax - $lngMin);
        $menor = min($this->area($a), $this->area($b));

        return $menor > 0 ? $comun / $menor : 0.0;
    }

    /** Punto dentro del polígono (regla del rayo). */
    private function contiene(array $poligono, float $lat, float $lng): bool
    {
        $dentro = false;
        $n = count($poligono);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$yi, $xi] = $poligono[$i];
            [$yj, $xj] = $poligono[$j];

            if ((($yi > $lat) !== ($yj > $lat)) && ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) {
                $dentro = ! $dentro;
            }
        }

        return $dentro;
    }

    /** Área en grados cuadrados (solo sirve para saber si el polígono es plano, no para medirlo). */
    private function area(array $p): float
    {
        $suma = 0.0;
        $n = count($p);
        for ($i = 0; $i < $n; $i++) {
            $suma += $p[$i][1] * $p[($i + 1) % $n][0] - $p[($i + 1) % $n][1] * $p[$i][0];
        }

        return abs($suma) / 2;
    }

    /** ¿Algún lado del polígono corta a otro lado que no es vecino suyo? */
    private function seCruza(array $p): bool
    {
        $n = count($p);

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $vecinos = ($j === $i + 1) || ($i === 0 && $j === $n - 1);
                if ($vecinos) {
                    continue;
                }

                if ($this->seIntersectan($p[$i], $p[($i + 1) % $n], $p[$j], $p[($j + 1) % $n])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function seIntersectan(array $a, array $b, array $c, array $d): bool
    {
        $o1 = $this->orientacion($a, $b, $c);
        $o2 = $this->orientacion($a, $b, $d);
        $o3 = $this->orientacion($c, $d, $a);
        $o4 = $this->orientacion($c, $d, $b);

        return $o1 * $o2 < 0 && $o3 * $o4 < 0;
    }

    private function orientacion(array $a, array $b, array $c): float
    {
        return ($b[0] - $a[0]) * ($c[1] - $a[1]) - ($b[1] - $a[1]) * ($c[0] - $a[0]);
    }
}
