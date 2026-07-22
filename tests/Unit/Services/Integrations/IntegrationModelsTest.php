<?php

use App\Services\Integrations\Support\ConfiguredStoresProvider;
use App\Services\Integrations\Support\IntegrationModels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
 * A fronteira entre o motor e os models da aplicação é config, não import. Estes testes
 * travam o contrato: nome errado falha alto e com mensagem útil, em vez de só sumir com
 * dado no tenant do cliente.
 */

class FakeIntegrationStore extends Model
{
    protected $table = 'fake_stores';

    /** @param  Builder<self>  $query */
    public function scopeVisible(Builder $query): void
    {
        $query->where('visible', true);
    }
}

test('resolve o model configurado para cada papel', function (): void {
    config(['integrations.models.store' => FakeIntegrationStore::class]);

    expect(IntegrationModels::store())->toBe(FakeIntegrationStore::class);
});

test('falha com mensagem explícita quando o model não está configurado', function (): void {
    config(['integrations.models.ean_reference' => null]);

    expect(fn () => IntegrationModels::eanReference())
        ->toThrow(RuntimeException::class, 'integrations.models.ean_reference');
});

test('falha quando o model configurado não existe', function (): void {
    config(['integrations.models.product' => 'App\\Models\\ModelQueNaoExiste']);

    expect(fn () => IntegrationModels::product())->toThrow(RuntimeException::class);
});

test('o model de tenant vem da config do spatie, não de integrations.models', function (): void {
    expect(IntegrationModels::tenant())->toBe(config('multitenancy.tenant_model'));
});

test('o provedor de lojas aplica o scope configurado', function (): void {
    config([
        'integrations.models.store' => FakeIntegrationStore::class,
        'integrations.store_scope' => 'visible',
    ]);

    $query = (new class extends ConfiguredStoresProvider
    {
        public function exposedQuery(): Builder
        {
            return $this->query();
        }
    })->exposedQuery();

    expect($query->toSql())->toContain('"visible" = ?');
});

test('o provedor de lojas falha alto quando o scope configurado não existe no model', function (): void {
    config([
        'integrations.models.store' => FakeIntegrationStore::class,
        'integrations.store_scope' => 'publicada',
    ]);

    expect(fn () => (new ConfiguredStoresProvider)->firstDocument())
        ->toThrow(RuntimeException::class, 'integrations.store_scope');
});
