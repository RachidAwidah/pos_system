<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OpenApiDocumentationTest extends TestCase
{
    public function test_every_v1_api_operation_is_documented(): void
    {
        $this->assertSame(0, Artisan::call('l5-swagger:generate'));

        $document = json_decode(
            file_get_contents(storage_path('api-docs/api-docs.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'v1/')) {
                continue;
            }

            $path = '/'.$route->uri();

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $this->assertArrayHasKey(
                    mb_strtolower($method),
                    $document['paths'][$path] ?? [],
                    "Missing OpenAPI operation: {$method} {$path}",
                );
            }
        }
    }

    public function test_swagger_uses_sanctum_bearer_authentication(): void
    {
        Artisan::call('l5-swagger:generate');

        $document = json_decode(
            file_get_contents(storage_path('api-docs/api-docs.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('http', $document['components']['securitySchemes']['sanctum']['type']);
        $this->assertSame('bearer', $document['components']['securitySchemes']['sanctum']['scheme']);
        $this->assertArrayNotHasKey('security', $document['paths']['/v1/login']['post']);
        $this->assertSame([['sanctum' => []]], $document['paths']['/v1/users']['get']['security']);
    }

    public function test_swagger_ui_is_available_outside_production(): void
    {
        Artisan::call('l5-swagger:generate');

        $this->get('/docs/api')
            ->assertOk()
            ->assertSee('POS System API');

        $this->get('/docs')
            ->assertOk()
            ->assertJsonPath('info.title', 'POS System API');
    }

    public function test_swagger_is_not_exposed_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        try {
            $this->get('/docs/api')->assertNotFound();
            $this->get('/docs')->assertNotFound();
        } finally {
            app()->detectEnvironment(fn (): string => 'testing');
        }
    }
}
