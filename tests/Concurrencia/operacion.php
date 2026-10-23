<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

// Proceso HTTP aislado para las pruebas MySQL; nunca usar contra una base operativa.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! str_ends_with(config('database.connections.mysql.database'), '_pruebas')) {
    throw new RuntimeException('Se exige una base descartable terminada en _pruebas.');
}
[$script, $barrera, $indice, $admin, $metodo, $url, $datos] = $argv;
Carbon::setTestNow(Carbon::today()->setTime(13, 0));
Auth::loginUsingId((int) $admin);
file_put_contents($barrera.'.'.$indice, 'listo');
$limite = microtime(true) + 20;
while (! is_file($barrera)) {
    if (microtime(true) > $limite) {
        throw new RuntimeException('La barrera no se liberó.');
    }
    usleep(10000);
}
$request = Request::create($url, $metodo, json_decode($datos, true), [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
if ($metodo === 'IMAGEN') {
    $request = Request::create($url, 'POST', json_decode($datos, true), [],
        ['images' => [UploadedFile::fake()->image('prueba.png', 10, 10)]], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
}
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
echo json_encode(['estado' => $response->getStatusCode()]);
$kernel->terminate($request, $response);
