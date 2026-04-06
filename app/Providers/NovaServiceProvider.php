<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Laravel\Nova\Nova;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\NovaApplicationServiceProvider;
use Laravel\Nova\Menu\MenuSection;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Nova::withBreadcrumbs();
        Nova::notificationPollingInterval(1);
        Nova::remoteScript(url('/js/nova-realtime-notifications.js'));
        Nova::style('crm-pipeline', asset('css/nova-pipeline.css'));
        Nova::script('crm-pipeline', asset('js/nova-client-pipeline.js'));
        Nova::script('crm-favicon', asset('js/nova-favicon.js'));
        $this->registerMainMenu();
    }

    protected function registerMainMenu(): void
    {
        Nova::mainMenu(function (Request $request): array {
            return [
                MenuSection::dashboard(\App\Nova\Dashboards\Main::class)->icon('home'),
                MenuSection::resource(\App\Nova\User::class)
                    ->icon('identification')
                    ->canSee(fn (Request $menuRequest): bool => $menuRequest->user()?->canManageTeam() ?? false),
                MenuSection::make('Clients', [
                    MenuItem::resource(\App\Nova\Client::class)->name('Business'),
                    MenuItem::resource(\App\Nova\Contact::class)->name('Contacts'),
                ])->icon('building-office')->collapsable(),
                MenuSection::make('Meetings', [
                    MenuItem::resource(\App\Nova\UpcomingMeeting::class)->name('Upcoming Meetings'),
                    MenuItem::resource(\App\Nova\DoneMeeting::class)->name('Done Meetings'),
                ])->icon('calendar-days')->collapsable(),
            ];
        });
    }

    /**
     * Register the configurations for Laravel Fortify.
     */
    protected function fortify(): void
    {
        Nova::fortify()
            ->features([
                Features::updatePasswords(),
                // Features::emailVerification(),
                // Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
            ])
            ->register();
    }

    /**
     * Register the Nova routes.
     */
    protected function routes(): void
    {
        Nova::routes()
            ->withAuthenticationRoutes(default: true)
            ->withPasswordResetRoutes()
            ->withoutEmailVerificationRoutes()
            ->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewNova', function (User $user) {
            return in_array($user->role, [UserRole::Salesperson, UserRole::TeamLeader, UserRole::Manager], true);
        });
    }

    /**
     * Get the dashboards that should be listed in the Nova sidebar.
     *
     * @return array<int, \Laravel\Nova\Dashboard>
     */
    protected function dashboards(): array
    {
        return [
            new \App\Nova\Dashboards\Main,
        ];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array<int, \Laravel\Nova\Tool>
     */
    public function tools(): array
    {
        return [];
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();

        //
    }
}
