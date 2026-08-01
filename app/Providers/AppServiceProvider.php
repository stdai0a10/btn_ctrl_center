<?php

namespace App\Providers;

use App\Models\Room;
use App\Models\Device;
use App\Policies\DevicePolicy;
use App\Policies\RoomPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Line\LineExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Room::class, RoomPolicy::class);
        Gate::policy(Device::class, DevicePolicy::class);

        Event::listen(SocialiteWasCalled::class, LineExtendSocialite::class.'@handle');
    }
}
