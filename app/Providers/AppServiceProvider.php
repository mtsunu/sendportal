<?php

declare(strict_types=1);

namespace App\Providers;

use App\Livewire\Setup;
use App\Mail\ThrottledSesAdapter;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use RuntimeException;
use Sendportal\Base\Facades\Sendportal;
use Sendportal\Base\Factories\MailAdapterFactory;
use Sendportal\Base\Models\EmailServiceType;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrap();

        // Route SES sends through the host's coordinated, rate-limited adapter.
        // The static map is read lazily at dispatch time, so this is boot-order safe.
        MailAdapterFactory::$adapterMap[EmailServiceType::SES] = ThrottledSesAdapter::class;

        Sendportal::setCurrentWorkspaceIdResolver(
            static function () {
                /** @var User $user */
                $user = auth()->user();
                $request = request();
                $workspaceId = null;

                if ($user && $user->currentWorkspaceId()) {
                    $workspaceId = $user->currentWorkspaceId();
                } elseif ($request && (($apiToken = $request->bearerToken()) || ($apiToken = $request->get('api_token')))) {
                    $workspaceId = ApiToken::resolveWorkspaceId($apiToken);
                }

                if (! $workspaceId) {
                    throw new RuntimeException('Current Workspace ID Resolver must not return a null value.');
                }

                return $workspaceId;
            }
        );

        Sendportal::setSidebarHtmlContentResolver(
            static function () {
                return view('layouts.sidebar.manageUsersMenuItem')->render()
                    . view('layouts.sidebar.sendersMenuItem')->render();
            }
        );

        Sendportal::setHeaderHtmlContentResolver(
            static function () {
                return view('layouts.header.userManagementHeader')->render();
            }
        );

        View::composer(
            ['sendportal::campaigns.create', 'sendportal::campaigns.edit'],
            static function (ViewContract $view): void {
                /** @var User|null $user */
                $user = auth()->user();

                $view->with(
                    'senders',
                    $user?->currentWorkspace()?->senders()->orderBy('label')->get() ?? collect()
                );
            }
        );

        Livewire::component('setup', Setup::class);
    }
}
