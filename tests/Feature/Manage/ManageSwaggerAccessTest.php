<?php

namespace Tests\Feature\Manage;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManageSwaggerAccessTest extends TestCase
{
    public function test_manage_swagger_routes_reject_requests_outside_the_allowed_network(): void
    {
        foreach ([
            '/manage/docs/swagger',
            '/manage/docs',
            '/manage/docs/asset/swagger-ui.css',
            '/manage/docs/oauth2-callback',
        ] as $uri) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
                ->get($uri)
                ->assertForbidden();
        }
    }

    public function test_manage_swagger_routes_allow_requests_from_the_allowed_network(): void
    {
        $docsPath = storage_path('framework/testing/manage-swagger-'.Str::uuid());

        File::ensureDirectoryExists($docsPath);
        File::put($docsPath.'/manage.json', '{"openapi":"3.0.0"}');
        config(['l5-swagger.defaults.paths.docs' => $docsPath]);

        try {
            $server = ['REMOTE_ADDR' => '127.0.0.1'];

            $this->withServerVariables($server)
                ->get('/manage/docs/swagger')
                ->assertOk();
            $this->withServerVariables($server)
                ->get('/manage/docs')
                ->assertOk()
                ->assertJson(['openapi' => '3.0.0']);
            $this->withServerVariables($server)
                ->get('/manage/docs/asset/swagger-ui.css')
                ->assertOk();
            $this->withServerVariables($server)
                ->get('/manage/docs/oauth2-callback')
                ->assertOk();
        } finally {
            File::deleteDirectory($docsPath);
        }
    }

    public function test_public_swagger_is_not_restricted_by_the_manage_ip_middleware(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/docs/swagger')
            ->assertOk();
    }

    public function test_each_swagger_ui_only_loads_its_own_definition(): void
    {
        $publicResponse = $this->get('/docs/swagger')->assertOk();
        $manageResponse = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/manage/docs/swagger')
            ->assertOk();
        $deviceResponse = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/device/docs/swagger')
            ->assertOk();

        $publicDocsUrl = route('l5-swagger.default.docs', 'api-docs.json');
        $manageDocsUrl = route('l5-swagger.manage.docs', 'manage.json');
        $deviceDocsUrl = route('l5-swagger.device.docs', 'device.json');

        $definitions = [
            [$publicResponse, $publicDocsUrl],
            [$manageResponse, $manageDocsUrl],
            [$deviceResponse, $deviceDocsUrl],
        ];

        foreach ($definitions as [$response, $expectedDocsUrl]) {
            $response
                ->assertSee('url: "'.$expectedDocsUrl.'"', false)
                ->assertDontSee('urls.push', false)
                ->assertDontSee('urls.primaryName', false);

            foreach ([$publicDocsUrl, $manageDocsUrl, $deviceDocsUrl] as $docsUrl) {
                if ($docsUrl !== $expectedDocsUrl) {
                    $response->assertDontSee($docsUrl, false);
                }
            }
        }
    }

    public function test_manage_swagger_uses_forwarded_client_ip_only_from_a_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.10']]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.10',
            'HTTP_X_FORWARDED_FOR' => '127.0.0.1',
        ])->get('/manage/docs/swagger')->assertOk();

        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.10',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
        ])->get('/manage/docs/swagger')->assertForbidden();

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_X_FORWARDED_FOR' => '127.0.0.1',
        ])->get('/manage/docs/swagger')->assertForbidden();
    }
}
