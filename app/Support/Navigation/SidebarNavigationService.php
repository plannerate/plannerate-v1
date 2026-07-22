<?php

namespace App\Support\Navigation;

use App\Models\Category;
use App\Models\Cluster;
use App\Models\EanReference;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Planogram;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SimilarGroup;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\UsefulLink;
use App\Models\User;
use App\Support\Authorization\PermissionName;
use App\Support\Navigation\Menu\Menu;
use App\Support\Navigation\Menu\MenuPayloadAdapter;
use Callcocam\LaravelIntegrations\Models\IntegrationApi;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Reoptimization\ReoptimizationInboxController;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplate;
use Illuminate\Http\Request;

class SidebarNavigationService
{
    public function __construct(
        private MenuPayloadAdapter $menuPayloadAdapter,
    ) {}

    /**
     * @return array{context: string, main: array<int, array<string, mixed>>}
     */
    public function build(Request $request): array
    {
        $menu = $this->resolveContextMenu($request);

        return $this->menuPayloadAdapter->toNavigation($menu, $request->user());
    }

    private function resolveContextMenu(Request $request): Menu
    {
        $landlordDomain = (string) config('app.landlord_domain');
        $currentTenantContainerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');
        $hasCurrentTenant = app()->bound($currentTenantContainerKey) && app($currentTenantContainerKey) !== null;
        $isTenantContext = $hasCurrentTenant && strtolower($request->getHost()) !== strtolower($landlordDomain);

        return $isTenantContext
            ? $this->tenantMenu()
            : $this->landlordMenu();
    }

    // Ícones usam nomes Lucide em kebab-case (ex: 'layout-grid', 'shield-check').
    // Ao adicionar um ícone novo, registre-o também em:
    // resources/js/components/NavMenuEntry.vue → import + iconMap
    private function landlordMenu(): Menu
    {
        return Menu::make('landlord')
            ->item('landlord.dashboard', function ($item): void {
                $item
                    ->label(__('app.navigation.dashboard'))
                    ->href(route('dashboard', absolute: false))
                    ->icon('layout-grid')
                    ->authorize('viewAny', Tenant::class)
                    ->setOrder(10);
            })
            ->group('landlord.business', function ($group): void {
                $group
                    ->label('Operacao')
                    ->setOrder(20)
                    ->item('landlord.plans', function ($item): void {
                        $item
                            ->label(__('app.landlord.plans.navigation'))
                            ->href(route('landlord.plans.index', absolute: false))
                            ->icon('package-open')
                            ->authorize('viewAny', Plan::class)
                            ->setOrder(10);
                    })
                    ->item('landlord.tenants', function ($item): void {
                        $item
                            ->label(__('app.landlord.tenants.navigation'))
                            ->href(route('landlord.tenants.index', absolute: false))
                            ->icon('building-2')
                            ->authorize('viewAny', Tenant::class)
                            ->setOrder(20);
                    })
                    ->item('landlord.modules', function ($item): void {
                        $item
                            ->label(__('app.landlord.modules.navigation'))
                            ->href(route('landlord.modules.index', absolute: false))
                            ->icon('blocks')
                            ->authorize('viewAny', Module::class)
                            ->setOrder(30);
                    })
                    ->item('landlord.integration-apis', function ($item): void {
                        $item
                            ->label(__('app.landlord.integration_apis.navigation'))
                            ->href(route('landlord.integration-apis.index', absolute: false))
                            ->icon('cable')
                            ->authorize('viewAny', IntegrationApi::class)
                            ->setOrder(35);
                    })
                    ->item('landlord.ean-references', function ($item): void {
                        $item
                            ->label(__('app.landlord.ean_references.navigation'))
                            ->href(route('landlord.ean-references.index', absolute: false))
                            ->icon('package')
                            ->authorize('viewAny', EanReference::class)
                            ->setOrder(40);
                    })
                    ->item('landlord.useful-links', function ($item): void {
                        $item
                            ->label(__('app.landlord.useful_links.navigation'))
                            ->href(route('landlord.useful-links.index', absolute: false))
                            ->icon('folder-kanban')
                            ->authorize('viewAny', UsefulLink::class)
                            ->setOrder(50);
                    })
                    // Sem authorize(): a ferramenta é client-side e não lê dado nenhum do
                    // sistema, então basta estar autenticado no landlord para usá-la.
                    ->item('landlord.proposal-generator', function ($item): void {
                        $item
                            ->label(__('app.landlord.proposal_generator.navigation'))
                            ->href(route('landlord.proposal-generator.index', absolute: false))
                            ->icon('file-text')
                            ->setOrder(60);
                    });
            })
            ->group('landlord.access', function ($group): void {
                $group
                    ->label('Acesso')
                    ->setOrder(30)
                    ->item('landlord.roles', function ($item): void {
                        $item
                            ->label(__('app.landlord.roles.navigation'))
                            ->href(route('landlord.roles.index', absolute: false))
                            ->icon('shield-check')
                            ->authorize('viewAny', Role::class)
                            ->setOrder(10);
                    })
                    ->item('landlord.users', function ($item): void {
                        $item
                            ->label(__('app.landlord.users.navigation'))
                            ->href(route('landlord.users.index', absolute: false))
                            ->icon('users')
                            ->authorize('viewAny', User::class)
                            ->setOrder(20);
                    })
                    ->item('landlord.permissions', function ($item): void {
                        $item
                            ->label(__('app.landlord.permissions.navigation'))
                            ->href(route('landlord.permissions.index', absolute: false))
                            ->icon('key-round')
                            ->authorize('viewAny', Permission::class)
                            ->setOrder(30);
                    });
            });
    }

    private function tenantMenu(): Menu
    {
        return Menu::make('tenant')
            ->item('tenant.dashboard', function ($item): void {
                $item
                    ->label(__('app.navigation.dashboard'))
                    ->href(route('tenant.dashboard', [], false))
                    ->icon('layout-grid')
                    ->authorize('viewAny', Tenant::class)
                    ->setOrder(10);
            })
            ->group('tenant.catalog', function ($group): void {
                $group
                    ->label('Cadastros')
                    ->setOrder(20)
                    ->item('tenant.products', function ($item): void {
                        $item
                            ->label(__('app.tenant.products.navigation'))
                            ->href(route('tenant.products.index', [], false))
                            ->icon('package')
                            ->authorize('viewAny', Product::class)
                            ->setOrder(10);
                    })
                    ->item('tenant.categories', function ($item): void {
                        $item
                            ->label(__('app.tenant.categories.navigation'))
                            ->href(route('tenant.categories.index', [], false))
                            ->icon('folder-tree')
                            ->authorize('viewAny', Category::class)
                            ->setOrder(20);
                    })
                    ->item('tenant.mercadologico', function ($item): void {
                        $item
                            ->label(__('app.landlord.mercadologico.navigation'))
                            ->href(route('tenant.mercadologico.index', [], false))
                            ->icon('list-tree')
                            ->authorize('viewAny', Category::class)
                            ->setOrder(25);
                    })
                    ->item('tenant.dimensions', function ($item): void {
                        $item
                            ->label('Dimensões')
                            ->href(route('tenant.dimensions.index', [], false))
                            ->icon('ruler')
                            ->authorize('viewAny', Product::class)
                            ->setOrder(30);
                    })
                    ->item('tenant.similar-groups', function ($item): void {
                        $item
                            ->label('Grupo de Similares')
                            ->href(route('tenant.similar-groups.index', [], false))
                            ->icon('layers')
                            ->authorize('viewAny', SimilarGroup::class)
                            ->setOrder(35);
                    })
                    ->item('tenant.providers', function ($item): void {
                        $item
                            ->label(__('app.tenant.providers.navigation'))
                            ->href(route('tenant.providers.index', [], false))
                            ->icon('truck')
                            ->authorize('viewAny', Provider::class)
                            ->setOrder(40);
                    });
            })
            ->group('tenant.operational', function ($group): void {
                $group
                    ->label('Operação')
                    ->setOrder(30)
                    ->item('tenant.stores', function ($item): void {
                        $item
                            ->label(__('app.tenant.stores.navigation'))
                            ->href(route('tenant.stores.index', [], false))
                            ->icon('store')
                            ->authorize('viewAny', Store::class)
                            ->setOrder(10);
                    })
                    ->item('tenant.clusters', function ($item): void {
                        $item
                            ->label(__('app.tenant.clusters.navigation'))
                            ->href(route('tenant.clusters.index', [], false))
                            ->icon('blocks')
                            ->authorize('viewAny', Cluster::class)
                            ->setOrder(20);
                    });
            })
            ->group('tenant.planograms-section', function ($group): void {
                $group
                    ->label('Planogramas')
                    ->setOrder(40)
                    ->item('tenant.planograms', function ($item): void {
                        $item
                            ->label('Gestão de Planogramas')
                            ->href(route('tenant.planograms.index', [], false))
                            ->icon('layout-template')
                            ->authorize('viewAny', Planogram::class)
                            ->setOrder(10);
                    })
                    ->item('tenant.editor.planograms', function ($item): void {
                        $item
                            ->label('Planogramas Clientes')
                            ->href(route('tenant.editor.planograms.index', [], false))
                            ->icon('eye')
                            ->authorize(PermissionName::TENANT_EDITOR_PLANOGRAMS_VIEW_ANY)
                            ->setOrder(20);
                    })
                    ->item('tenant.planogram-templates', function ($item): void {
                        $item
                            ->label('Templates Planogramas')
                            ->href(route('tenant.planogram-templates.index', [], false))
                            ->icon('file-spreadsheet')
                            ->authorize('viewAny', PlanogramTemplate::class)
                            ->setOrder(30);
                    })
                    // O badge é o que faz a reotimização existir para o usuário: sem ele, a
                    // proposta só apareceria abrindo o editor daquela gôndola, e um layout melhor
                    // apodreceria na fila sem ninguém saber que existe.
                    ->item('tenant.reoptimization', function ($item): void {
                        $item
                            ->label(__('plannerate.reoptimization.inbox.navigation'))
                            ->href(route('tenant.planograms.reoptimization.index', [], false))
                            ->icon('sparkles')
                            ->authorize('viewAny', Planogram::class)
                            ->badge(fn (): int => ReoptimizationInboxController::pendingCount())
                            ->setOrder(40);
                    });
            })
            ->group('tenant.analytics', function ($group): void {
                $group
                    ->label('Análises')
                    ->setOrder(50)
                    ->item('tenant.sales', function ($item): void {
                        $item
                            ->label(__('app.tenant.sales.navigation'))
                            ->href(route('tenant.sales.index', [], false))
                            ->icon('badge-dollar-sign')
                            ->authorize('viewAny', Sale::class)
                            ->setOrder(10);
                    });
            })
            ->group('tenant.control', function ($group): void {
                $group
                    ->label('Controle')
                    ->setOrder(60)
                    ->item('tenant.users', function ($item): void {
                        $item
                            ->label(__('app.tenant.users.navigation'))
                            ->href(route('tenant.users.index', [], false))
                            ->icon('users')
                            ->authorize('viewAny', User::class)
                            ->setOrder(10);
                    })
                    ->item('tenant.system-logs', function ($item): void {
                        $item
                            ->label(__('app.tenant.system-logs.navigation'))
                            ->href(route('tenant.system-logs.index', [], false))
                            ->icon('file-text')
                            ->authorize('viewAny', Product::class)
                            ->setOrder(20);
                    });
            });
    }
}
