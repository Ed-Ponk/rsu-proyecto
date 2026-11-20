<form method="POST" action="{{ route('admin.programaciones.store') }}" class="t-formulario" autocomplete="off" novalidate
    data-programacion-formulario
    data-url-validar="{{ route('admin.programaciones.validar') }}"
    data-url-plantilla="{{ url('admin/grupos-personal') }}">
    @csrf
    <x-admin.requisitos :faltan="$faltan ?? []" />

    <div class="t-alerta-form"></div>

    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="programacionInicio" class="form-label t-obligatorio">Fecha de inicio</label>
            <input type="date" name="inicio" id="programacionInicio" class="form-control" required min="{{ today()->toDateString() }}">
        </div>
        <div class="col-md-4">
            <label for="programacionFin" class="form-label t-obligatorio">Fecha de fin</label>
            <input type="date" name="fin" id="programacionFin" class="form-control" required min="{{ today()->toDateString() }}">
        </div>
        <div class="col-md-4">
            <button type="button" class="btn t-boton-contorno w-100" data-validar><i class="bi bi-check2 me-1"></i> Validar disponibilidad</button>
        </div>

        <div class="col-12">
            <label for="programacionGrupo" class="form-label t-obligatorio">Grupo de personal</label>
            <x-admin.buscador name="grupo_id" id="programacionGrupo" :url="route('admin.grupos.buscar')"
                placeholder="Buscar grupo por nombre, zona o turno" mensaje="Seleccione un grupo de la lista."
                vacio="No hay grupos de personal activos. Cree uno primero." cambiar="Cambiar grupo" data-grupo />
            <div class="form-text">Al elegir un grupo se traen su conductor, ayudantes y días; puede mantenerlos o cambiarlos antes de generar.</div>
        </div>

        <div class="col-12" data-resultado></div>

        <div class="col-12" data-datos-grupo hidden>
            <div class="t-resumen-grupo" data-resumen-grupo></div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="programacionConductor" class="form-label t-obligatorio">Conductor</label>
                    <x-admin.buscador name="conductor_id" id="programacionConductor" :url="route('admin.personal.buscar', ['contexto' => 'conductor'])"
                        placeholder="Buscar conductor por nombre o DNI" mensaje="Seleccione un conductor de la lista."
                        vacio="No hay conductores activos." cambiar="Cambiar conductor" data-conductor />
                </div>

                <div class="col-12">
                    <div class="row g-3" data-ranuras-ayudantes data-url="{{ route('admin.personal.buscar', ['contexto' => 'ayudante']) }}"></div>
                </div>

                <div class="col-12">
                    <label class="form-label t-obligatorio mb-1">Días de trabajo</label>
                    <div class="t-dias-semana" data-error-destino>
                        @foreach ($dias as $numero => $nombre)
                            <label class="t-dia">
                                <input type="checkbox" name="dias[]" value="{{ $numero }}">
                                <span>{{ $nombre }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="form-text">Los feriados activos se omiten automáticamente.</div>
                </div>

                <div class="col-12">
                    <label for="programacionNotas" class="form-label">Observaciones</label>
                    <textarea name="notas" id="programacionNotas" class="form-control" rows="2" maxlength="500"
                        placeholder="Observaciones adicionales..."></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="t-pie-form">
        <button type="button" class="btn t-boton-cancelar" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cancelar</button>
        <button type="submit" class="btn t-boton" data-guardar disabled><i class="bi bi-floppy-fill me-1"></i> Guardar</button>
    </div>
</form>
