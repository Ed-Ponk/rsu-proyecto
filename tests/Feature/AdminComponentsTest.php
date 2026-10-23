<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminComponentsTest extends TestCase
{
    public function test_cabecera_renders_title_subtitle_icon_and_actions(): void
    {
        $view = $this->blade(
            '<x-admin.cabecera titulo="Gestión de Vehículos" subtitulo="Supervisión de flota" icono="bi-truck-front" boton="Nuevo vehículo" boton-id="btnNuevo" />'
        );

        $view->assertSee('Gestión de Vehículos');
        $view->assertSee('Supervisión de flota');
        $view->assertSee('bi bi-truck-front');
        $view->assertSee('Nuevo vehículo');
        $view->assertSee('id="btnNuevo"', false);
    }

    public function test_cabecera_renders_breadcrumbs_when_provided(): void
    {
        $breadcrumbs = ['Inicio' => '/admin', 'Vehículos' => null];
        $view = $this->blade(
            '<x-admin.cabecera titulo="Vehículos" :breadcrumb="$bc" />',
            ['bc' => $breadcrumbs]
        );

        $view->assertSee('Inicio');
        $view->assertSee('href="/admin"', false);
        $view->assertSee('Vehículos');
    }

    public function test_tarjeta_renders_header_body_and_tools(): void
    {
        $view = $this->blade(
            '<x-admin.tarjeta titulo="Flota Municipal" icono="bi-truck" badge="14 Unidades">
                <x-slot:herramientas>
                    <button id="btnHerramienta">Filtros</button>
                </x-slot:herramientas>
                <p>Contenido del cuerpo</p>
                <x-slot:pie>
                    <span>Pie de tarjeta</span>
                </x-slot:pie>
            </x-admin.tarjeta>'
        );

        $view->assertSee('Flota Municipal');
        $view->assertSee('bi bi-truck');
        $view->assertSee('14 Unidades');
        $view->assertSee('Filtros');
        $view->assertSee('Contenido del cuerpo');
        $view->assertSee('Pie de tarjeta');
    }

    public function test_badge_renders_semantic_variants_and_dot(): void
    {
        $viewSuccess = $this->blade('<x-admin.badge variante="activo" dot texto="Operativo" />');
        $viewSuccess->assertSee('t-insignia t-verde', false);
        $viewSuccess->assertSee('Operativo');
        $viewSuccess->assertSee('rounded-circle', false);

        $viewDanger = $this->blade('<x-admin.badge variante="inactivo" texto="Baja" />');
        $viewDanger->assertSee('t-insignia t-rojo', false);
        $viewDanger->assertSee('Baja');

        $viewWarning = $this->blade('<x-admin.badge variante="por-vencer" texto="Por Vencer" />');
        $viewWarning->assertSee('t-insignia t-ambar', false);
        $viewWarning->assertSee('Por Vencer');

        $viewNeutral = $this->blade('<x-admin.badge variante="neutral" texto="Info" />');
        $viewNeutral->assertSee('t-insignia t-gris', false);
    }

    public function test_boton_renders_variants_and_link_mode(): void
    {
        $viewBtn = $this->blade('<x-admin.boton variante="primary" icono="bi-plus-lg" texto="Crear" />');
        $viewBtn->assertSee('<button', false);
        $viewBtn->assertSee('t-boton');
        $viewBtn->assertSee('bi bi-plus-lg');
        $viewBtn->assertSee('Crear');

        $viewLink = $this->blade('<x-admin.boton href="/admin/vehiculos" variante="secondary" texto="Volver" />');
        $viewLink->assertSee('<a', false);
        $viewLink->assertSee('href="/admin/vehiculos"', false);
        $viewLink->assertSee('Volver');

        $viewDanger = $this->blade('<x-admin.boton variante="danger" texto="Eliminar" />');
        $viewDanger->assertSee('t-boton-cancelar');
        $viewDanger->assertSee('t-boton-peligro');
    }

    public function test_modal_renders_proper_id_and_backdrop_attributes(): void
    {
        $view = $this->blade(
            '<x-admin.modal id="ventanaPrueba" titulo="Editar Registro" tamano="modal-xl">
                <form id="formularioPrueba">Campos</form>
            </x-admin.modal>'
        );

        $view->assertSee('id="ventanaPrueba"', false);
        $view->assertSee('modal-xl');
        $view->assertSee('Editar Registro');
        $view->assertSee('data-bs-backdrop="static"', false);
        $view->assertSee('Campos');
    }

    public function test_pie_formulario_renders_submit_and_cancel_buttons(): void
    {
        $view = $this->blade(
            '<x-admin.pie-formulario accion="Guardar Cambios" cancelar="Descartar" />'
        );

        $view->assertSee('Guardar Cambios');
        $view->assertSee('Descartar');
        $view->assertSee('data-bs-dismiss="modal"', false);
        $view->assertSee('type="submit"', false);
    }

    public function test_kpi_card_renders_metrics_and_accents(): void
    {
        $view = $this->blade(
            '<x-admin.kpi-card titulo="Flota Operativa" valor="12 / 14" icono="bi-truck" variante="esmeralda" subtexto="86% disponible" :progreso="86" />'
        );

        $view->assertSee('Flota Operativa');
        $view->assertSee('12 / 14');
        $view->assertSee('bi bi-truck');
        $view->assertSee('86% disponible');
        $view->assertSee('width: 86%', false);
    }

    public function test_empty_state_renders_humanized_message_and_action(): void
    {
        $view = $this->blade(
            '<x-admin.empty-state titulo="Sin registros" descripcion="No se encontraron datos en el sistema." accion="Nuevo registro" accion-id="btnCrear" />'
        );

        $view->assertSee('Sin registros');
        $view->assertSee('No se encontraron datos en el sistema.');
        $view->assertSee('Nuevo registro');
        $view->assertSee('id="btnCrear"', false);
        $view->assertSee('<svg', false);
    }

    public function test_alerta_renders_semantic_styles_and_dismissible(): void
    {
        $view = $this->blade(
            '<x-admin.alerta tipo="warning" titulo="Advertencia" mensaje="Revisar fechas de contrato." cerrable />'
        );

        $view->assertSee('alert alert-warning', false);
        $view->assertSee('Advertencia');
        $view->assertSee('Revisar fechas de contrato.');
        $view->assertSee('btn-close');
    }
}
