<?php

// php scripts/generar-inventario.php [--comprobar]. Solo fuentes; nunca lee .env o datos operativos.
$raiz = dirname(__DIR__);
$permitidos = ['app', 'bootstrap', 'config', 'database', 'docs', 'lang', 'resources', 'routes', 'scripts', 'tests', '.github'];
$archivos = [];
$simbolos = [];
$pruebas = [];
$recorrer = function (string $carpeta) use (&$recorrer, &$archivos, $raiz): void {
    foreach (new DirectoryIterator($carpeta) as $entrada) {
        if ($entrada->isDot() || $entrada->isLink()) {
            continue;
        }
        $relativo = str_replace('\\', '/', substr($entrada->getPathname(), strlen($raiz) + 1));
        if ($entrada->isDir()) {
            if (! in_array($entrada->getFilename(), ['cache', 'node_modules', 'vendor'], true)) {
                $recorrer($entrada->getPathname());
            }
        } elseif ($relativo !== 'docs/inventario-proyecto.json' && in_array(strtolower($entrada->getExtension()), ['php', 'js', 'css', 'json', 'xml', 'md', 'yml', 'yaml', 'svg'], true)) {
            $archivos[] = $relativo;
        }
    }
};
foreach ($permitidos as $carpeta) {
    if (is_dir($raiz.'/'.$carpeta)) {
        $recorrer($raiz.'/'.$carpeta);
    }
}
foreach (['public/images', 'public/uploads'] as $carpeta) {
    if ($carpeta === 'public/images' && is_dir($raiz.'/'.$carpeta)) {
        $recorrer($raiz.'/'.$carpeta);
    }
}
foreach (['README.md', 'DESIGN.md', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'phpunit.xml', 'phpunit.mysql.xml', 'vite.config.js', 'tailwind.config.js', 'postcss.config.js', '.env.example', '.editorconfig', '.gitignore', '.gitattributes', 'artisan'] as $archivo) {
    if (is_file($raiz.'/'.$archivo)) {
        $archivos[] = $archivo;
    }
}
sort($archivos);
foreach ($archivos as $archivo) {
    if (! str_ends_with($archivo, '.php')) {
        continue;
    }
    $contenido = file_get_contents($raiz.'/'.$archivo);
    $metodos = [];
    preg_match_all('/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $contenido, $coincidencias, PREG_OFFSET_CAPTURE);
    foreach ($coincidencias[1] as [$nombre,$posicion]) {
        $metodos[] = ['nombre' => $nombre, 'linea' => substr_count(substr($contenido, 0, $posicion), "\n") + 1];
    }
    preg_match_all('/^\s*(?:abstract\s+|final\s+)?(?:class|trait|interface)\s+(\w+)/m', $contenido, $clases);
    preg_match_all("/Schema::create\('([^']+)'/", $contenido, $tablas);
    $simbolos[] = ['archivo' => $archivo, 'clases' => $clases[1], 'metodos' => $metodos, 'tablas_creadas' => $tablas[1]];
    if (str_starts_with($archivo, 'tests/') && str_ends_with($archivo, 'Test.php')) {
        $pruebas[] = ['archivo' => $archivo, 'casos' => array_values(array_filter($metodos, fn ($m) => str_starts_with($m['nombre'], 'test_')))];
    }
}
$inventario = ['fecha' => (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d'),
    'zona_horaria' => 'America/Lima', 'alcance' => 'Fuentes propias; sin secretos, dependencias, cargas de usuario, caches o compilados.',
    'archivos' => $archivos, 'simbolos_php' => $simbolos, 'pruebas' => $pruebas];
$destino = $raiz.'/docs/inventario-proyecto.json';
if (in_array('--comprobar', $argv, true)) {
    $actual = json_decode(file_get_contents($destino), true, flags: JSON_THROW_ON_ERROR);
    unset($actual['fecha'], $inventario['fecha']);
    if ($actual !== $inventario) {
        fwrite(STDERR, "El inventario necesita regenerarse.\n");
        exit(1);
    }
} else {
    file_put_contents($destino, json_encode($inventario, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
}
echo count($archivos)." archivos inventariados; referencias verificadas.\n";
