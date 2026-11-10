<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ErrorPageRenderingTest extends TestCase
{
    #[DataProvider('nativeErrorStatuses')]
    public function test_native_error_statuses_render_branded_recovery_pages(int $status, string $title): void
    {
        config(['app.debug' => false]);
        app()->setLocale('es');
        Route::get('/__error/{status}', fn (int $status) => abort($status));

        $response = $this->get("/__error/{$status}");

        $response->assertStatus($status)
            ->assertSee('BarberHub')
            ->assertSee($title)
            ->assertSee('Volver al inicio');
    }

    public static function nativeErrorStatuses(): array
    {
        return [
            'payment required' => [402, 'No se pudo completar la solicitud'],
            'not found' => [404, 'No encontramos esta página'],
            'method not allowed' => [405, 'Esta acción no está disponible'],
            'page expired' => [419, 'La página expiró'],
            'too many requests' => [429, 'Hay demasiadas solicitudes'],
            'server error' => [500, 'Ocurrió un problema inesperado'],
            'service unavailable' => [503, 'Servicio no disponible temporalmente'],
        ];
    }

    public function test_unmatched_url_renders_the_not_found_page(): void
    {
        app()->setLocale('es');

        $response = $this->get('/__missing-error-page');

        $response->assertNotFound()
            ->assertSee('No encontramos esta página')
            ->assertSee('Volver al inicio')
            ->assertSee('href="'.url('/').'"', false)
            ->assertDontSee('fonts.bunny.net')
            ->assertDontSee('/build/assets');
    }

    #[DataProvider('fallbackErrorStatuses')]
    public function test_fallback_statuses_render_their_branded_error_page(int $status, string $title): void
    {
        config(['app.debug' => false]);
        app()->setLocale('es');
        Route::get('/__fallback-error/{status}', fn (int $status) => abort($status));

        $response = $this->get("/__fallback-error/{$status}");

        $response->assertStatus($status)
            ->assertSee('BarberHub')
            ->assertSee($title);
    }

    public static function fallbackErrorStatuses(): array
    {
        return [
            'other client error' => [410, 'No se pudo completar la solicitud'],
            'other server error' => [501, 'Ocurrió un problema inesperado'],
        ];
    }

    public function test_server_error_page_never_discloses_exception_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/__sensitive-error', function (): never {
            throw new \RuntimeException('SENSITIVE-EXCEPTION-DETAIL');
        });

        $response = $this->get('/__sensitive-error');

        $response->assertInternalServerError()
            ->assertDontSee('SENSITIVE-EXCEPTION-DETAIL')
            ->assertDontSee('RuntimeException');
    }

    public function test_json_error_responses_keep_the_framework_contract(): void
    {
        config(['app.debug' => false]);
        Route::get('/__json-error', fn () => abort(404));

        $response = $this->getJson('/__json-error');

        $response->assertNotFound()
            ->assertJson(['message' => '']);
    }
}
