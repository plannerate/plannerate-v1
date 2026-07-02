<?php

use App\Support\Authorization\PermissionName;

test('every permission has a short name and description', function () {
    $metadata = PermissionName::metadata();

    expect($metadata)->toHaveCount(count(PermissionName::all()));

    foreach (PermissionName::all() as $name) {
        expect($metadata[$name]['short_name'])->not->toBe('', "short_name vazio para {$name}");
        expect($metadata[$name]['description'])->not->toBe('', "description vazia para {$name}");
    }
});

test('crud permissions derive short name and description from the resource map', function () {
    expect(PermissionName::shortNameFor('landlord.plans.create'))->toBe('Criar Planos');
    expect(PermissionName::descriptionFor('landlord.plans.create'))
        ->toBe('Permite cadastrar um plano de assinatura.');

    expect(PermissionName::shortNameFor('tenant.products.viewAny'))->toBe('Listar Produtos');
    expect(PermissionName::descriptionFor('tenant.products.delete'))
        ->toBe('Permite excluir um produto do catálogo.');
});

test('special permissions use explicit overrides', function () {
    expect(PermissionName::shortNameFor('tenant.gondolas.autogenerate.ia'))->toBe('Gerar Gôndola com IA');
    expect(PermissionName::shortNameFor('tenant.kanban.executions.move'))->toBe('Mover Cartão (Kanban)');
    expect(PermissionName::descriptionFor('tenant.dashboard.view'))
        ->toContain('painel principal');
});

test('unknown permissions return null metadata', function () {
    expect(PermissionName::shortNameFor('tenant.unknown.viewAny'))->toBeNull();
    expect(PermissionName::descriptionFor('foo'))->toBeNull();
});
