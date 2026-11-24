<?php

namespace App\Providers;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\CustomFieldService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CustomFieldService::class);
        $this->app->singleton(CheckoutService::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        View::composer('layouts.app', function ($view) {
            $user = Auth::user();
            $companies = Company::query()->whereIn('id', $user->companyIds())->orderBy('name')->get(['id', 'name', 'code']);
            $active = $companies->firstWhere('id', session('company_id'));
            $byKind = Asset::query()->notArchived()->when($active, fn ($q) => $q->where('assets.company_id', $active->id))
                ->join('models', 'models.id', '=', 'assets.model_id')->join('categories', 'categories.id', '=', 'models.category_id')
                ->selectRaw('categories.kind, count(*) as n')->groupBy('categories.kind')->pluck('n', 'kind');
            $view->with([
                'topCompanies' => $companies,
                'activeCompany' => $active,
                'navCounts' => [
                    'assets' => (int) (($byKind['device'] ?? 0) + ($byKind['phone'] ?? 0)),
                    'printers' => (int) ($byKind['printer'] ?? 0),
                    'servers' => (int) (($byKind['server'] ?? 0) + ($byKind['nas'] ?? 0)),
                    'lines' => (int) ($byKind['line'] ?? 0),
                    'accessories' => Accessory::query()->when($active, fn ($q) => $q->where('company_id', $active->id))->count(),
                    'users' => User::query()->when($active, fn ($q) => $q->where('company_id', $active->id))->count(),
                    'companies' => $active ? 1 : $companies->count(),
                ],
            ]);
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(config('inventario.api_throttle_per_minute', 120))
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}
