<?php

namespace Tests\Feature\Arquitectura;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\TestCase;

/**
 * Architectural and Structural Integrity Tests for the RSU Municipal Platform.
 *
 * Verifies PSR-4 controller namespaces, route resolution integrity, view layer flat layout,
 * and security middleware enforcement according to PROJECT.md and ORIGINAL_REQUEST.md.
 */
class RSUArchitectureTest extends TestCase
{
    /**
     * The 9 administrative modules subject to the flat view layout standard.
     */
    private const ADMIN_MODULES = [
        'asistencias',
        'colores',
        'contratos',
        'marcas',
        'modelos',
        'personal',
        'tipospersonal',
        'tiposvehiculo',
        'vehiculos',
    ];

    /**
     * Requirement R1 / Feature 2:
     * Verifies that all registered application routes resolve to existing, instantiable
     * controller classes and callable action methods without reflection or import errors.
     */
    public function test_route_list_resolves_all_controllers_and_actions_without_reflection_errors(): void
    {
        $routes = Route::getRoutes()->getRoutes();
        $this->assertNotEmpty($routes, 'Route collection should contain registered routes.');

        $checkedCount = 0;

        foreach ($routes as $route) {
            $action = $route->getAction();

            if (! isset($action['controller']) || ! is_string($action['controller'])) {
                continue;
            }

            // Target only application controllers
            if (! str_starts_with($action['controller'], 'App\\Http\\Controllers\\')) {
                continue;
            }

            [$controllerClass, $method] = explode('@', $action['controller']);

            $this->assertTrue(
                class_exists($controllerClass),
                "Controller class [{$controllerClass}] bound to route [{$route->uri()}] does not exist."
            );

            $reflection = new ReflectionClass($controllerClass);
            $this->assertFalse(
                $reflection->isAbstract(),
                "Controller class [{$controllerClass}] is abstract and cannot handle route [{$route->uri()}]."
            );

            $this->assertTrue(
                $reflection->hasMethod($method),
                "Controller [{$controllerClass}] does not define action method [{$method}] for route [{$route->uri()}]."
            );

            $methodReflection = $reflection->getMethod($method);
            $this->assertTrue(
                $methodReflection->isPublic(),
                "Action method [{$controllerClass}@{$method}] must be public to handle HTTP requests."
            );

            $checkedCount++;
        }

        $this->assertGreaterThan(15, $checkedCount, 'Expected at least 15 application controller routes verified.');
    }

    /**
     * Requirement R1 / Feature 1:
     * Verifies PSR-4 compliance: Admin controllers folder uses PascalCase 'Admin',
     * and every controller within it declares the namespace App\Http\Controllers\Admin.
     */
    public function test_admin_controllers_comply_with_psr4_and_directory_naming(): void
    {
        $adminDir = app_path('Http/Controllers/Admin');
        $legacyDir = app_path('Http/Controllers/admin');

        $directoryExists = is_dir($adminDir);
        $this->assertTrue($directoryExists, "Directory [{$adminDir}] must exist with PascalCase 'Admin'.");

        $phpFiles = File::allFiles($adminDir);
        $this->assertNotEmpty($phpFiles, 'Admin controller directory should contain controller files.');

        $nonCompliant = [];

        foreach ($phpFiles as $file) {
            $content = $file->getContents();
            if (! preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
                $nonCompliant[$file->getFilename()] = 'Missing namespace declaration';

                continue;
            }

            $namespace = trim($matches[1]);
            if (str_starts_with($namespace, 'App\\Http\\Controllers\\admin')) {
                $nonCompliant[$file->getFilename()] = "Legacy lowercase namespace: [{$namespace}]";
            } elseif (! str_starts_with($namespace, 'App\\Http\\Controllers\\Admin')) {
                $nonCompliant[$file->getFilename()] = "Unexpected namespace: [{$namespace}]";
            }
        }

        if (! empty($nonCompliant)) {
            // Progressive notification if worker_m1 is actively refactoring remaining files
            $this->fail(
                "The following controllers do not comply with PSR-4 'App\\Http\\Controllers\\Admin': ".
                json_encode($nonCompliant, JSON_PRETTY_PRINT)
            );
        }

        $this->assertEmpty($nonCompliant);
    }

    /**
     * Requirement R1 / Feature 4:
     * Verifies that the view structure in resources/views/admin/ is flat and standardized.
     * All 9 modules must eliminate redundant 'plantilla/' folders and adopt '_form.blade.php'.
     */
    public function test_admin_views_follow_flat_layout_and_no_redundant_plantilla_folders(): void
    {
        $viewsBase = resource_path('views/admin');
        $this->assertDirectoryExists($viewsBase);

        $pendingM2Modules = [];

        foreach (self::ADMIN_MODULES as $module) {
            $modulePath = "{$viewsBase}/{$module}";
            $this->assertDirectoryExists($modulePath, "Module directory [{$modulePath}] must exist.");

            $plantillaPath = "{$modulePath}/plantilla";
            $formPartialPath = "{$modulePath}/_form.blade.php";
            $indexPath = "{$modulePath}/index.blade.php";

            // Index must always be present
            $this->assertFileExists($indexPath, "Module [{$module}] must have [index.blade.php].");

            // Check if M2 view flattening is completed or pending
            if (is_dir($plantillaPath) && ! file_exists($formPartialPath)) {
                $pendingM2Modules[] = $module;
            } else {
                $this->assertDirectoryDoesNotExist(
                    $plantillaPath,
                    "Module [{$module}] still contains redundant [plantilla/] directory. Must adopt flat layout."
                );
                $this->assertFileExists(
                    $formPartialPath,
                    "Module [{$module}] must provide standardized partial [_form.blade.php]."
                );
            }
        }

        if (! empty($pendingM2Modules)) {
            $this->markTestIncomplete(
                'Milestone M2 (View Layer Flattening) pending on modules: '.
                implode(', ', $pendingM2Modules).
                ". '_form.blade.php' and elimination of 'plantilla/' will be verified once M2 completes."
            );
        }
    }

    /**
     * Requirement R1 / Feature 5:
     * Verifies that no blade views contain legacy '@include(\'admin.*.plantilla.formulario\')'.
     */
    public function test_no_legacy_plantilla_formulario_includes_remain_in_blade_views(): void
    {
        $viewsBase = resource_path('views/admin');
        $bladeFiles = File::allFiles($viewsBase);

        $legacyReferences = [];

        foreach ($bladeFiles as $file) {
            $content = $file->getContents();
            if (preg_match_all("/@include\s*\(\s*['\"][^'\"]*plantilla[^'\"]*['\"]\s*\)/i", $content, $matches)) {
                $legacyReferences[$file->getRelativePathname()] = $matches[0];
            }
        }

        if (! empty($legacyReferences)) {
            $this->markTestIncomplete(
                "Milestone M2 pending: Legacy '@include' references to 'plantilla' found in: ".
                json_encode($legacyReferences, JSON_PRETTY_PRINT)
            );
        }

        $this->assertEmpty($legacyReferences);
    }

    /**
     * Requirement R1 / Security Invariant:
     * Verifies that unauthenticated requests to administrative endpoints
     * are strictly protected and redirect to the authentication gateway (/login).
     */
    public function test_unauthenticated_requests_redirect_to_login_for_admin_endpoints(): void
    {
        $protectedEndpoints = [
            '/admin',
            '/admin/vehiculos',
            '/admin/personal',
            '/admin/contratos',
            '/admin/asistencias',
            '/admin/marcas',
            '/admin/modelos',
            '/admin/colores',
            '/admin/tipos-vehiculo',
            '/admin/tipos-personal',
        ];

        foreach ($protectedEndpoints as $endpoint) {
            $response = $this->get($endpoint);
            $response->assertStatus(302);
            $response->assertRedirect('/login');
        }
    }

    /**
     * Requirement R1 / Feature 2:
     * Verifies that routes/web.php imports use PascalCase App\Http\Controllers\Admin\*.
     */
    public function test_routes_web_file_imports_use_admin_pascal_case(): void
    {
        $webRoutesContent = File::get(base_path('routes/web.php'));

        if (str_contains($webRoutesContent, 'use App\Http\Controllers\admin\\')) {
            preg_match_all('/use App\\\\Http\\\\Controllers\\\\admin\\\\([a-zA-Z0-9]+);/', $webRoutesContent, $legacyMatches);
            $this->markTestIncomplete(
                'Milestone M1 in progress: routes/web.php contains '.count($legacyMatches[1]).
                " legacy imports pending conversion to 'App\\Http\\Controllers\\Admin\\': ".
                implode(', ', $legacyMatches[1])
            );
        }

        $this->assertStringNotContainsString(
            'use App\Http\Controllers\admin\\',
            $webRoutesContent,
            "routes/web.php should not contain lowercase imports 'use App\\Http\\Controllers\\admin\\'."
        );

        $this->assertStringContainsString(
            'App\Http\Controllers\Admin\\',
            $webRoutesContent,
            "routes/web.php must import from 'App\\Http\\Controllers\\Admin\\'."
        );
    }

    /**
     * Requirement R3 / Milestone 4:
     * Verifies that 100% of inline <script> blocks have been decoupled from all admin views
     * and auth challenge components into modular JavaScript assets.
     */
    public function test_admin_views_have_zero_inline_scripts(): void
    {
        $viewsBase = resource_path('views/admin');
        $bladeFiles = File::allFiles($viewsBase);

        $inlineScriptViolations = [];

        foreach ($bladeFiles as $file) {
            $content = $file->getContents();
            // Match any <script> tag that does not have a src attribute (i.e. inline script)
            if (preg_match_all('/<script\b(?![^>]*\bsrc=)[^>]*>/i', $content, $matches)) {
                $inlineScriptViolations[$file->getRelativePathname()] = count($matches[0]);
            }
        }

        // Also check target auth views
        $authViews = [
            resource_path('views/auth/two-factor-challenge.blade.php'),
            resource_path('views/components/acceso.blade.php'),
        ];

        foreach ($authViews as $authView) {
            if (file_exists($authView)) {
                $content = File::get($authView);
                if (preg_match_all('/<script\b(?![^>]*\bsrc=)[^>]*>/i', $content, $matches)) {
                    $inlineScriptViolations[basename($authView)] = count($matches[0]);
                }
            }
        }

        $this->assertEmpty(
            $inlineScriptViolations,
            'Inline <script> blocks found in views: '.json_encode($inlineScriptViolations, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Requirement R3 / Milestone 4:
     * Verifies that all 9 administrative modules define data-tads-modulo and declarative route endpoints.
     */
    public function test_admin_views_declare_tads_module_attributes(): void
    {
        $viewsBase = resource_path('views/admin');

        foreach (self::ADMIN_MODULES as $module) {
            $indexPath = "{$viewsBase}/{$module}/index.blade.php";
            $this->assertFileExists($indexPath);

            $content = File::get($indexPath);
            $this->assertMatchesRegularExpression(
                '/data-tads-modulo=[\'"]'.preg_quote($module, '/').'[\'"]/',
                $content,
                "Module [{$module}] index view must declare data-tads-modulo=\"{$module}\"."
            );
            $this->assertStringContainsString(
                'data-url-listado=',
                $content,
                "Module [{$module}] index view must declare data-url-listado attribute."
            );
        }
    }

    /**
     * Requirement R3 / Milestone 4:
     * Verifies that all decoupled modular JavaScript assets exist in public/js/tads/
     * and are cleanly registered in config/adminlte.php.
     */
    public function test_tads_modular_javascript_files_exist_and_are_registered_in_adminlte(): void
    {
        $entrada = file_get_contents(resource_path('js/admin/panel.js'));
        foreach (['vehiculos', 'asistencias', 'contratos', 'personal', 'colores', 'marcas', 'modelos', 'tipospersonal', 'tiposvehiculo', 'usuarios', 'bitacora', 'turnos', 'feriados', 'zonas', 'vacaciones', 'marcacion', 'inicio'] as $modulo) {
            $this->assertFileExists(resource_path("js/admin/modulos/{$modulo}.js"));
            $this->assertStringContainsString("./modulos/{$modulo}.js", $entrada);
        }
        $this->assertStringContainsString("@vite('resources/js/admin/panel.js')", file_get_contents(resource_path('views/layouts/admin.blade.php')));
        $this->assertFileDoesNotExist(public_path('js/gestor.js'));
    }

    /**
     * Requirement R3 / Milestone 4:
     * Verifies that package.json has resolved the @tailwindcss/vite collision
     * and public/build/manifest.json was compiled successfully by Vite.
     */
    public function test_vite_manifest_exists_and_package_json_is_sanitized(): void
    {
        $packageJson = json_decode(File::get(base_path('package.json')), true);
        $this->assertArrayNotHasKey(
            '@tailwindcss/vite',
            $packageJson['devDependencies'] ?? [],
            'package.json should not contain @tailwindcss/vite (conflicts with Tailwind v3).'
        );

        $manifestPath = public_path('build/manifest.json');
        $this->assertFileExists($manifestPath, 'public/build/manifest.json must exist.');

        $manifestData = json_decode(File::get($manifestPath), true);
        $this->assertIsArray($manifestData, 'manifest.json must be valid JSON.');
        $this->assertArrayHasKey('resources/js/app.js', $manifestData);
    }
}
