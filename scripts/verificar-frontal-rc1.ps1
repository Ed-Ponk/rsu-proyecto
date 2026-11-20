param([uri] $Origen = 'http://127.0.0.1:8114')

$ErrorActionPreference = 'Stop'
if (!$Origen.IsAbsoluteUri -or $Origen.Scheme -notin 'http','https' -or
    $Origen.AbsolutePath -ne '/' -or $Origen.Query -or $Origen.Fragment -or $Origen.UserInfo) {
    throw 'Usa únicamente el origen autorizado, sin ruta ni credenciales.'
}
$base = $Origen.GetLeftPart([UriPartial]::Authority)
$resultados = foreach ($caso in @(
    @{ nombre='salud'; ruta='/api/v1/salud'; esperado=200 },
    @{ nombre='capa-vial'; ruta='/mapas/chiclayo-vial.json'; esperado=200 },
    @{ nombre='panel-privado'; ruta='/admin'; esperado=401 },
    @{ nombre='perfil-conductor-privado'; ruta='/api/conductor/perfil'; esperado=401 },
    @{ nombre='perfil-ciudadano-privado'; ruta='/api/v1/ciudadano/perfil'; esperado=401 },
    @{ nombre='archivo-de-entorno'; ruta='/.env'; esperado=404 }
)) {
    $respuesta = Invoke-WebRequest ($base + $caso.ruta) -Headers @{Accept='application/json'} -TimeoutSec 10 -SkipHttpErrorCheck
    $cumple = $respuesta.StatusCode -eq $caso.esperado
    if ($caso.nombre -eq 'salud' -and $cumple) {
        $salud = $respuesta.Content | ConvertFrom-Json
        $cumple = $salud.servicio -eq 'rsu' -and $salud.version -eq 3
    }
    if ($caso.nombre -eq 'capa-vial' -and $cumple) {
        $mapa = $respuesta.Content | ConvertFrom-Json
        $cumple = $mapa.vias.Count -gt 0 -and $mapa.licencia -eq 'ODbL-1.0'
    }
    [pscustomobject]@{ prueba=$caso.nombre; http=$respuesta.StatusCode; cumple=$cumple }
}
$resultados
if ($resultados | Where-Object { !$_.cumple }) { throw 'Fallaron comprobaciones del frontal.' }
