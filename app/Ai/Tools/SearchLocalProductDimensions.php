<?php

namespace App\Ai\Tools;

use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Etapa 1 do pipeline: busca por produto semelhante já aprovado no banco do tenant.
 * Usa filtros rígidos de categoria/unidade/conteúdo/embalagem + similaridade semântica (pgvector).
 */
class SearchLocalProductDimensions implements Tool
{
    public function description(): Stringable|string
    {
        return 'Busca produtos similares com dimensões já aprovadas no banco de dados do supermercado. '.
               'Use sempre como primeira etapa antes de consultar APIs externas.';
    }

    public function handle(Request $request): Stringable|string
    {
        $categoryId = (string) ($request['category_id'] ?? '');
        $measurementUnit = (string) ($request['measurement_unit'] ?? '');
        $netContent = isset($request['net_content']) ? (float) $request['net_content'] : null;
        $packagingType = (string) ($request['packaging_type'] ?? '');
        $description = (string) ($request['description'] ?? '');

        $query = Product::withApprovedDimensions()
            ->when($categoryId !== '', fn ($q) => $q->where('category_id', $categoryId))
            ->when($measurementUnit !== '', fn ($q) => $q->where('measurement_unit', $measurementUnit))
            ->when($packagingType !== '', fn ($q) => $q->where('packaging_type', $packagingType))
            ->when(
                $netContent !== null,
                fn ($q) => $q->whereBetween('net_content', [
                    $netContent * 0.95,
                    $netContent * 1.05,
                ])
            );

        if ($description !== '' && $this->pgvectorAvailable()) {
            $candidates = $query
                ->whereVectorSimilarTo('description_embedding', $description, minSimilarity: 0.75)
                ->select(['id', 'name', 'ean', 'brand', 'width', 'height', 'depth', 'weight', 'unit',
                    'dimension_source', 'dimension_confidence', 'category_id'])
                ->limit(5)
                ->get();
        } else {
            $candidates = $query
                ->select(['id', 'name', 'ean', 'brand', 'width', 'height', 'depth', 'weight', 'unit',
                    'dimension_source', 'dimension_confidence', 'category_id'])
                ->limit(5)
                ->get();
        }

        if ($candidates->isEmpty()) {
            return json_encode(['found' => false]);
        }

        $results = $candidates->map(fn (Product $p): array => [
            'product_id' => $p->id,
            'ean' => $p->ean,
            'description' => $p->name,
            'brand' => $p->brand,
            'dimensions' => [
                'width' => (float) $p->width,
                'height' => (float) $p->height,
                'depth' => (float) $p->depth,
                'weight' => (float) $p->weight,
                'unit' => $p->unit,
            ],
            'source' => $p->dimension_source,
            'confidence' => $p->dimension_confidence,
        ])->all();

        return json_encode(['found' => true, 'candidates' => $results]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'description' => $schema->string()
                ->description('Descrição completa do produto: nome, marca, embalagem, conteúdo')
                ->required(),
            'category_id' => $schema->string()
                ->description('ID da categoria do produto (ULID) — use o valor do campo "Categoria ID" no prompt'),
            'measurement_unit' => $schema->string()
                ->description('Unidade de medida: KG, G, L, ML, UN, etc.')
                ->required(),
            'net_content' => $schema->number()
                ->description('Conteúdo líquido numérico para filtro ±5%'),
            'packaging_type' => $schema->string()
                ->description('Tipo de embalagem: saco, PET, vidro, caixa, pote, lata, tetrapak'),
        ];
    }

    private function pgvectorAvailable(): bool
    {
        return Str::of(
            config('database.connections.tenant.driver', '')
        )->is('pgsql');
    }
}
