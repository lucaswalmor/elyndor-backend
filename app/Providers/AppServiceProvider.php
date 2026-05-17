<?php

namespace App\Providers;

use App\Events\MatchFinished;
use App\Listeners\ApplyMatchProgressionRewards;
use App\Listeners\ApplyPlayerMatchStats;
use App\Listeners\ApplyRankedMatchOutcome;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Broadcast::routes(['middleware' => ['auth:sanctum']]);
        require base_path('routes/channels.php');

        Event::listen(MatchFinished::class, ApplyMatchProgressionRewards::class);
        Event::listen(MatchFinished::class, ApplyRankedMatchOutcome::class);
        Event::listen(MatchFinished::class, ApplyPlayerMatchStats::class);
    }
}
