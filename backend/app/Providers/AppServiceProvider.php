<?php

namespace App\Providers;

use App\Events\UserActivityLogged;
use App\Listeners\AwardBadgesListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Laravel's event auto-discovery would normally pick this up on its own,
        // but registering explicitly here so it's obvious where the badge engine
        // actually gets wired in without having to go hunting for it.
        Event::listen(UserActivityLogged::class, AwardBadgesListener::class);
    }
}
