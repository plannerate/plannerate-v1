<?php

namespace App\Providers;

use App\Contracts\ProductImageAiEditor;
use App\Models\Category;
use App\Models\Cluster;
use App\Models\EanReference;
use App\Models\Gondola;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Planogram;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowTemplate;
use App\Observers\TenantObserver;
use App\Policies\CategoryPolicy;
use App\Policies\ClusterPolicy;
use App\Policies\EanReferencePolicy;
use App\Policies\GondolaPolicy;
use App\Policies\ModulePolicy;
use App\Policies\PermissionPolicy;
use App\Policies\PlanogramPolicy;
use App\Policies\PlanPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProviderPolicy;
use App\Policies\RolePolicy;
use App\Policies\StorePolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use App\Policies\WorkflowExecutionPolicy;
use App\Policies\WorkflowTemplatePolicy;
use App\Services\OpenAiProductImageEditor;
use App\Support\Navigation\Menu\Contracts\ResolvesMenuAuthorization;
use App\Support\Navigation\Menu\MenuAuthorizationResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ResolvesMenuAuthorization::class, MenuAuthorizationResolver::class);
        $this->app->bind(ProductImageAiEditor::class, OpenAiProductImageEditor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureRuntimeDirectories();
        Tenant::observe(TenantObserver::class);
        $this->registerPolicies();
        $this->configureDefaults();
    }

    /**
     * Ensure runtime cache directories exist and view cache path is always valid.
     */
    protected function ensureRuntimeDirectories(): void
    {
        $paths = [
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            base_path('bootstrap/cache'),
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }
        }

        $compiledPath = config('view.compiled');
        if (! is_string($compiledPath) || trim($compiledPath) === '') {
            config(['view.compiled' => storage_path('framework/views')]);
        }
    }

    /**
     * Register application authorization policies.
     */
    protected function registerPolicies(): void
    {
        Gate::before(function (User $user): ?bool {
            return $user->roles()
                ->where('system_name', 'super-admin')
                ->exists()
                ? true
                : null;
        });

        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Module::class, ModulePolicy::class);
        Gate::policy(EanReference::class, EanReferencePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Store::class, StorePolicy::class);
        Gate::policy(Cluster::class, ClusterPolicy::class);
        Gate::policy(Provider::class, ProviderPolicy::class);
        Gate::policy(Planogram::class, PlanogramPolicy::class);
        Gate::policy(Gondola::class, GondolaPolicy::class);
        Gate::policy(WorkflowTemplate::class, WorkflowTemplatePolicy::class);
        Gate::policy(WorkflowGondolaExecution::class, WorkflowExecutionPolicy::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
