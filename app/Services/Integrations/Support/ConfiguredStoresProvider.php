<?php

namespace App\Services\Integrations\Support;

use App\Services\Integrations\Contracts\StoresProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Implementação padrão do {@see StoresProvider}: o model vem de `integrations.models.store`,
 * o critério de elegibilidade de `integrations.store_scope` (um scope local do model) e a
 * coluna do documento de `integrations.store_document_column`.
 *
 * Cobre o caso da aplicação sem escrever código; quem precisar de outro critério rebinda
 * o contrato no container.
 */
class ConfiguredStoresProvider implements StoresProvider
{
    public function stores(): array
    {
        $documentColumn = self::documentColumn();

        return $this->query()
            ->get(['id', $documentColumn])
            ->map(fn (Model $store): array => [
                'id' => (string) $store->getKey(),
                'document' => self::digits($store->getAttribute($documentColumn)),
            ])
            ->filter(fn (array $store): bool => $store['document'] !== '')
            ->values()
            ->all();
    }

    public function firstDocument(): ?string
    {
        $documentColumn = self::documentColumn();

        $document = $this->query()
            ->whereNotNull($documentColumn)
            ->value($documentColumn);

        $digits = self::digits($document);

        return $digits !== '' ? $digits : null;
    }

    /**
     * Query do model de loja com o scope de elegibilidade aplicado.
     */
    protected function query(): Builder
    {
        $query = IntegrationModels::query('store');
        $scope = config('integrations.store_scope', 'published');

        if (! is_string($scope) || $scope === '') {
            return $query;
        }

        if (! method_exists($query->getModel(), 'scope'.Str::studly($scope))) {
            throw new RuntimeException(sprintf(
                'integrations.store_scope aponta para o scope "%s", que %s não implementa.',
                $scope,
                $query->getModel()::class,
            ));
        }

        return $query->{$scope}();
    }

    protected static function documentColumn(): string
    {
        $column = config('integrations.store_document_column', 'document');

        return is_string($column) && $column !== '' ? $column : 'document';
    }

    protected static function digits(mixed $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }
}
