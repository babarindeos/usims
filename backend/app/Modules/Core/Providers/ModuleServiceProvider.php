<?php

namespace App\Modules\Core\Providers;

use App\Modules\Audit\Services\AuditContext;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccessService;
use App\Modules\Workflow\Events\WorkflowCompleted;
use App\Modules\Workflow\Listeners\NotifyInitiatorOfOutcome;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Wires every module: migrations, routes, the scoped permission gate and shared services. */
class ModuleServiceProvider extends ServiceProvider
{
    public const MODULES = ['Identity', 'Institution', 'Audit', 'Workflow', 'Notifications'];

    public function register(): void
    {
        $this->app->scoped(AuditContext::class);
        $this->app->scoped(AccessService::class);
    }

    public function boot(): void
    {
        foreach (self::MODULES as $module) {
            $this->loadMigrationsFrom(app_path("Modules/{$module}/Database/Migrations"));

            $routes = app_path("Modules/{$module}/routes.php");
            if (is_file($routes)) {
                Route::middleware('api')->prefix('api/v1')->group($routes);
            }
        }

        // Abilities shaped like "module.action" are permission codes checked with scope.
        Gate::before(function ($user, string $ability, array $arguments) {
            if (! $user instanceof User || ! str_contains($ability, '.')) {
                return null;
            }

            return app(AccessService::class)->allows($user, $ability, $arguments[0] ?? null);
        });

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)->by($request->user()?->id ?: $request->ip()));

        Event::listen(WorkflowCompleted::class, NotifyInitiatorOfOutcome::class);
    }
}
