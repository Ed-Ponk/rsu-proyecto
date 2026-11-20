#Requires -Version 7.2
param(
    [ValidateSet('Diagnosticar','Preparar','Iniciar','Verificar','Detener','Respaldar','Restaurar')]
    [string] $Accion = 'Diagnosticar',
    [string] $Nginx,
    [string] $Php = 'php.exe',
    [string] $PhpCgi = 'php-cgi.exe',
    [string] $Composer = 'composer',
    [string] $Mysql = 'mysql',
    [string] $MysqlDump = 'mysqldump',
    [string] $Base = $env:DB_DATABASE,
    [string] $Repositorio = (Join-Path $PSScriptRoot '..'),
    [string] $Respaldo,
    [string] $Sha256,
    [ValidateRange(1024,65535)][int] $Puerto = 8114,
    [ValidateRange(1024,65535)][int] $PuertoCgi = 9114,
    [uri] $Origen = "http://127.0.0.1:$Puerto"
)

$ErrorActionPreference = 'Stop'
if ($Accion -eq 'Diagnosticar') {
    foreach ($herramienta in $Php,$PhpCgi,$Composer,'node','npm',$Mysql,$MysqlDump,'docker','cloudflared','tailscale','scrcpy') {
        [pscustomobject]@{
            comprobacion = $herramienta
            disponible = [bool](Get-Command $herramienta -ErrorAction SilentlyContinue | Select-Object -First 1)
        }
    }
    foreach ($red in Get-NetConnectionProfile) {
        [pscustomobject]@{ interfaz = $red.InterfaceAlias; perfil = $red.NetworkCategory; ipv4 = $red.IPv4Connectivity }
    }
    foreach ($puertoLocal in $Puerto,$PuertoCgi) {
        [pscustomobject]@{ puerto = $puertoLocal; ocupado = [bool](Get-NetTCPConnection -LocalPort $puertoLocal -State Listen -ErrorAction SilentlyContinue) }
    }
    return
}

$scripts = Join-Path (Resolve-Path -LiteralPath $Repositorio).Path 'scripts'
if ($Accion -eq 'Verificar') {
    & (Join-Path $PSScriptRoot 'verificar-frontal-rc1.ps1') -Origen $Origen
    return
}
if ($Accion -in 'Iniciar','Detener') {
    if (!$Nginx) { throw 'Indica la ruta de nginx.exe en una instalación dedicada.' }
    & (Join-Path $PSScriptRoot 'frontal-rc1.ps1') -Accion $Accion -Nginx $Nginx -PhpCgi $PhpCgi -Repositorio $Repositorio -Puerto $Puerto -PuertoCgi $PuertoCgi
    return
}

if ($Base -notmatch '^rsu_v3_demo(?:_[a-z0-9_]+)?$' -or
    $env:DB_HOST -ne '127.0.0.1' -or !$env:DB_PASSWORD -or !$env:DB_USERNAME) {
    throw 'Indica un esquema MySQL de demostración dedicado en loopback y credenciales propias.'
}

Push-Location $Repositorio
try {
    switch ($Accion) {
        Preparar {
            if (!$env:APP_KEY -or !$env:ADMIN_EMAIL -or !$env:ADMIN_PASSWORD -or $env:RSU_DEMO_PASSWORD.Length -lt 12) {
                throw 'Define APP_KEY, ADMIN_EMAIL, ADMIN_PASSWORD y RSU_DEMO_PASSWORD en la sesión privada.'
            }
            $env:APP_ENV = 'local'
            $env:APP_DEBUG = 'false'
            $env:DB_CONNECTION = 'mysql'
            $env:DB_DATABASE = $Base
            & $Composer install --no-interaction --prefer-dist
            if ($LASTEXITCODE) { throw 'Composer no instaló las dependencias fijadas.' }
            npm ci
            if ($LASTEXITCODE) { throw 'npm no instaló las dependencias fijadas.' }
            npm run build
            if ($LASTEXITCODE) { throw 'Falló la compilación de los recursos del panel.' }
            & (Join-Path $scripts 'demo-v3.ps1') -Accion Preparar -Base $Base -Php $Php
        }
        Respaldar {
            & (Join-Path $scripts 'demo-v3.ps1') -Accion Respaldar -Base $Base -MysqlDump $MysqlDump
        }
        Restaurar {
            & (Join-Path $scripts 'demo-v3.ps1') -Accion Restaurar -Base $Base -Mysql $Mysql -Respaldo $Respaldo -Sha256 $Sha256
        }
    }
} finally { Pop-Location }

