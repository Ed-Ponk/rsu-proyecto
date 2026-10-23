{{-- Conserva los includes existentes y utiliza una sola implementación del encabezado. --}}
<x-admin.cabecera :titulo="$titulo" :icono="$icono ?? null" :subtitulo="$subtitulo ?? null" :boton="$boton ?? null" />
