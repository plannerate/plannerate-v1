<?php

use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Navigation\Menu\Contracts\ResolvesMenuAuthorization;
use App\Support\Navigation\Menu\Menu;
use App\Support\Navigation\Menu\MenuPayloadAdapter;
use Illuminate\Contracts\Auth\Authenticatable;

class FakeAuthorizationResolver implements ResolvesMenuAuthorization
{
    public function __construct(
        private readonly bool $allow = true,
        private readonly array $denyAbilities = [],
    ) {}

    public function allows(?Authenticatable $user, ?string $ability = null, mixed $subject = null): bool
    {
        if ($ability !== null && in_array($ability, $this->denyAbilities, true)) {
            return false;
        }

        return $this->allow;
    }
}

test('menu builder creates group submenu separator and item structure', function () {
    $menu = Menu::make('landlord')
        ->group('principal', function ($group): void {
            $group
                ->label('Principal')
                ->setOrder(10)
                ->item('dashboard', function ($item): void {
                    $item
                        ->label('Painel')
                        ->href('/dashboard')
                        ->icon('layout-grid')
                        ->authorize('viewAny', Tenant::class)
                        ->setOrder(10);
                });
        })
        ->separator('divider', 20)
        ->submenu('registries', function ($submenu): void {
            $submenu
                ->label('Cadastros')
                ->icon('folder-kanban')
                ->setOrder(30)
                ->item('plans', function ($item): void {
                    $item
                        ->label('Planos')
                        ->href('/plans')
                        ->authorize('viewAny', Plan::class)
                        ->setOrder(10);
                })
                ->item('tenants', function ($item): void {
                    $item
                        ->label('Tenants')
                        ->href('/tenants')
                        ->authorize('viewAny', Tenant::class)
                        ->setOrder(20);
                });
        });

    $payload = (new MenuPayloadAdapter(new FakeAuthorizationResolver))->toNavigation($menu, null);

    expect($payload['context'])->toBe('landlord')
        ->and($payload['main'])->toHaveCount(3)
        ->and($payload['main'][0]['type'])->toBe('group')
        ->and($payload['main'][1]['type'])->toBe('separator')
        ->and($payload['main'][2]['type'])->toBe('submenu')
        ->and($payload['main'][2]['children'])->toHaveCount(2);
});

test('menu payload adapter filters denied abilities and removes empty submenu', function () {
    $menu = Menu::make('landlord')
        ->item('dashboard', function ($item): void {
            $item
                ->label('Painel')
                ->href('/dashboard')
                ->authorize('viewAny', Tenant::class);
        })
        ->submenu('registries', function ($submenu): void {
            $submenu
                ->label('Cadastros')
                ->item('plans', function ($item): void {
                    $item
                        ->label('Planos')
                        ->href('/plans')
                        ->authorize('create', Plan::class);
                });
        });

    $payload = (new MenuPayloadAdapter(new FakeAuthorizationResolver(allow: true, denyAbilities: ['create'])))
        ->toNavigation($menu, null);

    expect($payload['main'])->toHaveCount(1)
        ->and($payload['main'][0]['type'])->toBe('item');
});
