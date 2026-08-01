<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceSwaggerAccessTest extends TestCase
{
    public function test_device_swagger_routes_reject_requests_outside_the_allowed_network(): void
    {
        foreach ([
            '/device/docs/swagger',
            '/device/docs',
            '/device/docs/asset/swagger-ui.css',
            '/device/docs/oauth2-callback',
        ] as $uri) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
                ->get($uri)
                ->assertForbidden();
        }
    }

    public function test_device_swagger_routes_allow_requests_from_the_allowed_network(): void
    {
        $docsPath = storage_path('framework/testing/device-swagger-'.Str::uuid());

        File::ensureDirectoryExists($docsPath);
        File::put($docsPath.'/device.json', '{"openapi":"3.0.0"}');
        config(['l5-swagger.defaults.paths.docs' => $docsPath]);

        try {
            $server = ['REMOTE_ADDR' => '127.0.0.1'];

            $this->withServerVariables($server)
                ->get('/device/docs/swagger')
                ->assertOk();
            $this->withServerVariables($server)
                ->get('/device/docs')
                ->assertOk()
                ->assertJson(['openapi' => '3.0.0']);
            $this->withServerVariables($server)
                ->get('/device/docs/asset/swagger-ui.css')
                ->assertOk();
            $this->withServerVariables($server)
                ->get('/device/docs/oauth2-callback')
                ->assertOk();
        } finally {
            File::deleteDirectory($docsPath);
        }
    }

    public function test_device_swagger_uses_forwarded_client_ip_only_from_a_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.10']]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.10',
            'HTTP_X_FORWARDED_FOR' => '127.0.0.1',
        ])->get('/device/docs/swagger')->assertOk();

        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.10',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
        ])->get('/device/docs/swagger')->assertForbidden();

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_X_FORWARDED_FOR' => '127.0.0.1',
        ])->get('/device/docs/swagger')->assertForbidden();
    }
}
