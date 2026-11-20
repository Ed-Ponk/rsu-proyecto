param(
    [ValidateSet('Iniciar', 'Estado', 'Detener')][string] $Accion = 'Estado',
    [Parameter(Mandatory)][string] $Nginx,
    [string] $Repositorio = (Join-Path $PSScriptRoot '..'),
    [string] $PhpCgi = 'php-cgi',
    [ValidateRange(1024,65535)][int] $Puerto = 8114,
    [ValidateRange(1024,65535)][int] $PuertoCgi = 9114
)

$ErrorActionPreference = 'Stop'
$nginxExe = (Get-Item -LiteralPath $Nginx).FullName
$prefijo = (Split-Path $nginxExe -Parent).Replace('\', '/') + '/'
$repo = (Resolve-Path -LiteralPath $Repositorio).Path
$estado = Join-Path $prefijo 'logs/rsu-rc1.json'
$config = 'conf/rsu-rc1.conf'
if ($Puerto -eq $PuertoCgi) { throw 'El frontal y PHP-CGI deben usar puertos distintos.' }
$origen = "http://127.0.0.1:$Puerto"
$escuchaCgi = "127.0.0.1:$PuertoCgi"

if ($Accion -eq 'Estado') {
    Invoke-RestMethod "$origen/api/v1/salud" -TimeoutSec 5
    return
}
if ($Accion -eq 'Detener') {
    if (!(Test-Path -LiteralPath $estado)) { return }
    $registrado = Get-Content -LiteralPath $estado -Raw | ConvertFrom-Json
    & $nginxExe -p $prefijo -c $config -s quit
    if ($LASTEXITCODE) { throw 'Nginx no confirmó la orden de parada.' }
    $cgi = Get-CimInstance Win32_Process -Filter "ProcessId=$($registrado.cgi)"
    $direccionRegistrada = if ($registrado.escuchaCgi) { $registrado.escuchaCgi } else { '127.0.0.1:9114' }
    if ($cgi -and $cgi.ExecutablePath -eq $registrado.ejecutable -and
        $cgi.CommandLine -match ('-b ' + [regex]::Escape($direccionRegistrada) + '(?:\s|$)')) {
        Stop-Process -Id $registrado.cgi
    }
    Remove-Item -LiteralPath $estado
    return
}

if (Test-Path -LiteralPath $estado) { throw 'Ya existe una instancia registrada.' }
if (Get-NetTCPConnection -LocalPort $Puerto,$PuertoCgi -State Listen -ErrorAction SilentlyContinue) {
    throw 'Uno de los puertos del frontal está ocupado.'
}
if (!(Test-Path -LiteralPath (Join-Path $repo 'vendor/autoload.php')) -or
    !(Test-Path -LiteralPath (Join-Path $repo 'public/build/manifest.json'))) {
    throw 'Instala las dependencias y compila los recursos antes de arrancar.'
}
if ($env:DB_DATABASE -notmatch '^rsu_v3_demo(?:_[a-z0-9_]+)?$' -or $env:DB_HOST -ne '127.0.0.1') {
    throw 'Selecciona un esquema MySQL de demostración en loopback.'
}
$cgiExe = (Get-Command $PhpCgi -CommandType Application).Source
$publico = (Join-Path $repo 'public').Replace('\', '/')
if ($publico -match '["$;]') { throw 'La ruta contiene caracteres que Nginx no puede interpolar con seguridad.' }
(Get-Content -LiteralPath (Join-Path $PSScriptRoot 'nginx-rc1.conf') -Raw).
    Replace('root html/public;', ('root "' + $publico + '";')).
    Replace('127.0.0.1:8114;', "127.0.0.1:$Puerto;").
    Replace('127.0.0.1:9114;', "$escuchaCgi;") |
    Set-Content -LiteralPath (Join-Path $prefijo $config) -Encoding utf8
& $nginxExe -p $prefijo -c $config -t
if ($LASTEXITCODE) { throw 'La configuración de Nginx no pasó la validación.' }

$env:APP_DEBUG = 'false'
$env:APP_URL = $origen
$env:DB_CONNECTION = 'mysql'
$cgi = Start-Process $cgiExe -ArgumentList '-b',$escuchaCgi -WorkingDirectory $repo -WindowStyle Hidden -PassThru
$frontal = $null
try {
    $frontal = Start-Process $nginxExe -ArgumentList '-p',('"' + $prefijo + '"'),'-c',$config -WorkingDirectory $prefijo -WindowStyle Hidden -PassThru
    $salud = $null
    for ($intento = 0; $intento -lt 10; $intento++) {
        try { $salud = Invoke-RestMethod "$origen/api/v1/salud" -TimeoutSec 2; break }
        catch { Start-Sleep -Milliseconds 300 }
    }
    if ($salud.servicio -ne 'rsu') { throw 'El frontal no confirmó la salud de RSU.' }
    @{cgi=$cgi.Id;ejecutable=$cgiExe;escuchaCgi=$escuchaCgi} | ConvertTo-Json | Set-Content -LiteralPath $estado
    $salud
} catch {
    if ($frontal -and !$frontal.HasExited) { & $nginxExe -p $prefijo -c $config -s quit }
    if (!$cgi.HasExited) { Stop-Process -InputObject $cgi }
    throw
}
