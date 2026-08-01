<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeviceApiRoutingTest extends TestCase
{
    public function test_device_only_apis_use_the_device_api_prefix(): void
    {
        $uris = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route): string => $route->uri())
            ->all();

        foreach ([
            'device/api/device-auth/long-token',
            'device/api/devices/{serial_number}/access-tokens',
            'device/api/devices/{serial_number}/poll',
            'device/api/device-jobs/{button_action_job_public_id}/progress',
            'device/api/device-jobs/{button_action_job_public_id}/complete',
        ] as $uri) {
            $this->assertContains($uri, $uris);
        }

        foreach ([
            'api/device-auth/long-token',
            'api/devices/{serial_number}/access-tokens',
            'api/devices/{serial_number}/poll',
            'api/device-jobs/{button_action_job_public_id}/progress',
            'api/device-jobs/{button_action_job_public_id}/complete',
        ] as $uri) {
            $this->assertNotContains($uri, $uris);
        }

        $this->assertContains('api/rooms/{room}/devices', $uris);
    }
}
