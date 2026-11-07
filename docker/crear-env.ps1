# Crea el archivo de variables para Docker con claves aleatorias (Windows PowerShell).
#
#   Computadora nueva (sin .env):        powershell -ExecutionPolicy Bypass -File docker\crear-env.ps1
#   Si ya tiene un .env de desarrollo:   powershell -ExecutionPolicy Bypass -File docker\crear-env.ps1 -Destino .env.docker
#                                        y luego:  docker compose --env-file .env.docker up -d
param([string]$Destino = '.env')
$ErrorActionPreference = 'Stop'
$destinoCompleto = Join-Path (Get-Location) $Destino
if (Test-Path $destinoCompleto) { Write-Host "Ya existe ${Destino}: no se modifica. Borrelo si quiere generarlo de nuevo."; exit 0 }

function Clave([int]$largo = 24) {
    $letras = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'
    -join (1..$largo | ForEach-Object { $letras[(Get-Random -Maximum $letras.Length)] })
}
# La clave del administrador siempre lleva mayusculas, minusculas y numeros.
$admin = 'Rsu' + (Clave 12) + '7'

$plantilla = [System.IO.File]::ReadAllText((Join-Path $PSScriptRoot '..\.env.docker.example'), [System.Text.Encoding]::UTF8)
$plantilla = $plantilla.Replace('CAMBIAR_CLAVE_ADMIN', $admin).Replace('CAMBIAR_CLAVE_BD', (Clave 24)).Replace('CAMBIAR_CLAVE_ROOT', (Clave 24))
# UTF-8 sin BOM: asi Docker Compose lee bien la primera variable.
[System.IO.File]::WriteAllText($destinoCompleto, $plantilla, (New-Object System.Text.UTF8Encoding($false)))

Write-Host "Archivo ${Destino} creado."
Write-Host "Administrador:  admin@rsu.local"
Write-Host "Contrasena:     $admin"
Write-Host "Guardela: no se vuelve a mostrar (esta en el archivo ${Destino})."
