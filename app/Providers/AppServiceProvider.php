<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Meeting;
use App\Models\User;
use App\Policies\ClientPolicy;
use App\Policies\ContactPolicy;
use App\Policies\MeetingPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Meeting::class, MeetingPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Event::listen(RequestHandled::class, function (): void {
            if ($this->app->runningUnitTests()) {
                return;
            }

            if (! Cache::add('meetings:notify-expired:throttle', 1, now()->addSeconds(55))) {
                return;
            }

            Artisan::call('meetings:notify-expired');
        });
    }
}
