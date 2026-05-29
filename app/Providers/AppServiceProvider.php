<?php

namespace App\Providers;

use App\Contracts\Integration\EventPublisher;
use App\Contracts\Integration\PortalAuthClient;
use App\Events\AnnouncementPublished;
use App\Services\Integration\DeorisPortalAuthClient;
use App\Services\Integration\EventHubPublisher;
use App\Events\MessageSent;
use App\Events\PostCreated;
use App\Listeners\BroadcastAnnouncementNotification;
use App\Listeners\NotifyBoardActivity;
use App\Listeners\NotifyMessageParticipants;
use App\Models\Announcement;
use App\Models\BoardPost;
use App\Observers\AnnouncementObserver;
use App\Observers\BoardPostObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PortalAuthClient::class, DeorisPortalAuthClient::class);
        $this->app->bind(EventPublisher::class, EventHubPublisher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers
        Announcement::observe(AnnouncementObserver::class);
        BoardPost::observe(BoardPostObserver::class);

        Event::listen(AnnouncementPublished::class, BroadcastAnnouncementNotification::class);
        Event::listen(PostCreated::class, NotifyBoardActivity::class);
        Event::listen(\App\Events\MessageSent::class, NotifyMessageParticipants::class);

        Broadcast::routes([
            'middleware' => [
                \App\Http\Middleware\ValidateSSOToken::class,
                \App\Http\Middleware\BlockStudents::class,
            ],
        ]);

        RateLimiter::for('careerconnect-api', function (Request $request) {
            $key = $request->user('faculty')?->id ?: $request->ip();

            return Limit::perMinute(config('careerconnect.rate_limit.api_per_minute', 120))
                ->by($key);
        });

        // Enable query log in debug mode
        if (config('app.debug')) {
            \DB::listen(function ($query) {
                \Log::debug($query->sql, $query->bindings);
            });
        }
    }

    private function repinEnvFromFile(): void
    {
        $envFile = base_path('.env');
        if (! is_readable($envFile)) { return; }
        $pin = ['APP_KEY', 'APP_ENV', 'SESSION_DRIVER', 'SESSION_COOKIE',
                'SESSION_DOMAIN', 'SESSION_SECURE_COOKIE', 'SESSION_SAME_SITE',
                'BROADCAST_CONNECTION', 'DB_CONNECTION', 'DB_DATABASE'];
        $map = [
            'APP_KEY'               => 'app.key',
            'APP_ENV'               => 'app.env',
            'SESSION_DRIVER'        => 'session.driver',
            'SESSION_COOKIE'        => 'session.cookie',
            'SESSION_SAME_SITE'     => 'session.same_site',
            'SESSION_SECURE_COOKIE' => 'session.secure',
            'BROADCAST_CONNECTION'  => 'broadcasting.default',
            'DB_DATABASE'           => 'database.connections.mysql.database',
        ];
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line === '' || $line[0] === '#') { continue; }
            $eq = strpos($line, '=');
            if ($eq === false) { continue; }
            $key = trim(substr($line, 0, $eq));
            if (! in_array($key, $pin, true)) { continue; }
            $val = trim(substr($line, $eq + 1));
            if (strlen($val) >= 2 && $val[0] === '"' && $val[-1] === '"') { $val = substr($val, 1, -1); }
            elseif (strlen($val) >= 2 && $val[0] === "'" && $val[-1] === "'") { $val = substr($val, 1, -1); }
            $_SERVER[$key] = $val;
            if (isset($map[$key])) { config([$map[$key] => $val]); }
        }
    }
}
