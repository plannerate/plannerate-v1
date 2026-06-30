<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Reports;

use Callcocam\LaravelRaptorPlannerate\Models\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GondolaReportService
{
    /**
     * Gera os dados do relatório para uma gôndola específica
     */
    public function generateReportData($gondolaId): array
    {
        try {
            $startTime = microtime(true);
            Log::info('🚀 INICIANDO geração de dados do relatório', [
                'gondola_id' => $gondolaId,
            ]);

            // Buscar a gôndola com relacionamentos otimizados
            Log::info('🔍 Buscando gôndola no banco de dados...');

            $gondola = Gondola::with([
                'sections' => function ($query) {
                    $query->orderBy('ordering');
                },
                'sections.shelves' => function ($query) {
                    $query->orderBy('ordering');
                },
                'sections.shelves.segments' => function ($query) {
                    $query->orderBy('ordering');
                },
                'sections.shelves.segments.layer',
                'sections.shelves.segments.layer.product',
                // Dimensões (width/height/depth) e imagem (url) são colunas diretas do Product,
                // não relações — não há eager-load para elas.
                'sections.shelves.segments.layer.product.category',
            ])->find($gondolaId);

            Log::info('✅ Gôndola carregada do banco de dados');

            if (! $gondola) {
                throw new \Exception("Gôndola com ID {$gondolaId} não encontrada.");
            }

            $reportData = [
                'gondola_id' => $gondola->id,
                'gondola_name' => $gondola->name ?? "Gôndola {$gondola->id}",
                'generated_at' => Carbon::now()->format('d/m/Y H:i:s'),
                'products' => [],
                'summary' => [
                    'total_products' => 0,
                    'total_sections' => 0,
                    'total_shelves' => 0,
                    'total_segments' => 0,
                    'total_units' => 0,
                ],
            ];

            $totalUnits = 0;
            $productCount = 0;

            // Percorrer seções da gôndola
            foreach ($gondola->sections as $sectionIndex => $section) {
                $reportData['summary']['total_sections']++;

                // Percorrer prateleiras da seção (já ordenadas pelo campo ordering)
                foreach ($section->shelves as $shelfIndex => $shelf) {
                    $reportData['summary']['total_shelves']++;

                    // Percorrer segmentos da prateleira
                    foreach ($shelf->segments as $segmentIndex => $segment) {
                        $reportData['summary']['total_segments']++;

                        // Verificar se existe produto no layer
                        if (isset($segment->layer) && isset($segment->layer->product)) {
                            $product = $segment->layer->product;
                            $layer = $segment->layer;

                            // Calcular unidades totais para este produto
                            $fronts = $layer->quantity ?? 1;
                            $heightUnits = $segment->quantity ?? 1; // Empilhamento vem do segment quantity

                            // Calcular unidades de profundidade: profundidade da prateleira / profundidade do produto
                            // Profundidade do produto é a coluna direta `depth` (relação Dimension foi removida)
                            $depthUnits = 1; // Padrão
                            if ($product->depth && $shelf->shelf_depth) {
                                $depthUnits = floor($shelf->shelf_depth / $product->depth);
                                // Garantir que seja pelo menos 1
                                $depthUnits = max(1, $depthUnits);
                            }

                            $productTotalUnits = $fronts * $heightUnits * $depthUnits;

                            $totalUnits += $productTotalUnits;
                            $productCount++;

                            // Calcular número da prateleira baseado na ordem visual (de cima para baixo)
                            // No planograma visual, a prateleira 1 está no topo, então invertemos a ordem
                            $totalShelvesInSection = $section->shelves->count();
                            $visualShelfNumber = $totalShelvesInSection - $shelfIndex;

                            // Adicionar produto aos dados do relatório
                            $reportData['products'][] = [
                                'nome_planograma' => $reportData['gondola_name'],
                                'fluxo' => $this->determineFlowDirection($gondola),
                                'modulo' => 'MÓDULO '.($sectionIndex + 1),
                                'prateleira' => $shelf->name ?? 'Prateleira '.$visualShelfNumber,
                                'id_produto' => $product->id,
                                'ean' => $product->ean ?? '',
                                'nome' => $product->name ?? 'Produto sem nome',
                                'frentes' => $fronts,
                                'unidades_altura' => $heightUnits,
                                'unidades_profundidade' => $depthUnits,
                                'total_unidades' => $productTotalUnits,
                                // Dados adicionais para contexto
                                'categoria' => $this->getProductCategoryName($product), // Nome da categoria
                                'departamento' => $this->getProductDepartmentName($product), // Nome do departamento
                                'codigo_erp' => $product->codigo_erp ?? '',
                                'has_image' => $this->checkProductHasImage($product), // Verifica se tem imagem
                                'has_dimension' => ! is_null($product->depth), // Verifica se tem dimensões (coluna direta)
                                'image_url' => $product->image_url ?? null, // URL da imagem
                                'position_section' => $sectionIndex + 1,
                                'position_shelf' => $shelfIndex + 1,
                                'position_segment' => $segmentIndex + 1,
                                'source' => 'gondola', // Identificar origem
                            ];
                        }
                    }
                }
            }

            // Adicionar produtos da biblioteca (que não estão na gôndola)
            Log::info('📚 Buscando produtos da biblioteca...');
            $libraryProducts = $this->getLibraryProducts($gondola, $reportData['products']);
            Log::info('📚 Produtos da biblioteca carregados', [
                'library_products_count' => count($libraryProducts),
            ]);
            $reportData['products'] = array_merge($reportData['products'], $libraryProducts);

            // Atualizar sumário
            $reportData['summary']['total_products'] = $productCount;
            $reportData['summary']['total_units'] = $totalUnits;

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime), 2);

            Log::info('✅ CONCLUÍDA geração de dados do relatório', [
                'gondola_id' => $gondolaId,
                'total_products' => count($reportData['products']),
                'duration_seconds' => $duration,
            ]);

            return $reportData;
        } catch (\Exception $e) {
            Log::error('Erro ao gerar dados do relatório da gôndola:', [
                'gondola_id' => $gondolaId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Determina a direção do fluxo baseado nas configurações da gôndola
     */
    private function determineFlowDirection($gondola): string
    {
        // Verificar campo flow primeiro e traduzir se necessário
        if (! empty($gondola->flow)) {
            return $this->translateFlowDirection($gondola->flow);
        }

        // Verificar se há configurações específicas de fluxo
        if (isset($gondola->flow_direction)) {
            return $this->translateFlowDirection($gondola->flow_direction);
        }

        // Verificar metadata ou configurações
        if (isset($gondola->metadata['flow'])) {
            return $this->translateFlowDirection($gondola->metadata['flow']);
        }

        // Verificar alignment para inferir direção
        if (isset($gondola->alignment)) {
            switch ($gondola->alignment) {
                case 'left':
                    return 'Esquerda para Direita';
                case 'right':
                    return 'Direita para Esquerda';
                case 'center':
                    return 'Centro';
                default:
                    return 'Padrão';
            }
        }

        // Padrão se não houver configuração específica
        return 'Esquerda para Direita';
    }

    /**
     * Traduz direções de fluxo do inglês para português
     */
    private function translateFlowDirection($flow): string
    {
        $translations = [
            'left_to_right' => 'Esquerda para Direita',
            'right_to_left' => 'Direita para Esquerda',
            'top_to_bottom' => 'Cima para Baixo',
            'bottom_to_top' => 'Baixo para Cima',
            'center' => 'Centro',
            'default' => 'Padrão',
        ];

        return $translations[strtolower($flow)] ?? $flow;
    }

    /**
     * Gera estatísticas adicionais da gôndola
     */
    public function generateGondolaStatistics($gondolaId): array
    {
        $reportData = $this->generateReportData($gondolaId);

        $stats = [
            'total_products' => $reportData['summary']['total_products'],
            'total_units' => $reportData['summary']['total_units'],
            'average_units_per_product' => $reportData['summary']['total_products'] > 0
                ? round($reportData['summary']['total_units'] / $reportData['summary']['total_products'], 2)
                : 0,
            'products_by_section' => [],
            'products_by_category' => [],
        ];

        // Agrupar por seção
        foreach ($reportData['products'] as $product) {
            $section = $product['modulo'];
            if (! isset($stats['products_by_section'][$section])) {
                $stats['products_by_section'][$section] = 0;
            }
            $stats['products_by_section'][$section]++;
        }

        // Agrupar por categoria
        foreach ($reportData['products'] as $product) {
            $category = $product['categoria'];
            if (! isset($stats['products_by_category'][$category])) {
                $stats['products_by_category'][$category] = 0;
            }
            $stats['products_by_category'][$category]++;
        }

        return $stats;
    }

    /**
     * Verifica se o produto tem imagem usando múltiplos critérios
     */
    private function checkProductHasImage($product): bool
    {
        // Critério 1: a imagem é a coluna direta `url` (não há relação `image`)
        if (! empty($product->url)) {
            return true;
        }

        // Critério 2: image_url existe e não é a imagem padrão de fallback
        $imageUrl = $product->image_url;

        return $imageUrl && ! str_contains($imageUrl, 'fall4.jpg');
    }

    /**
     * Obtém o nome da categoria do produto
     */
    private function getProductCategoryName($product): string
    {
        // Se tem categoria direta, usar o nome dela
        if ($product->category) {
            return $product->category->name;
        }

        return 'N/A';
    }

    /**
     * Obtém o nome do departamento do produto através da hierarquia de categoria
     */
    private function getProductDepartmentName($product): string
    {
        // Se tem categoria, buscar o departamento na hierarquia (nível 2)
        if ($product->category) {
            $hierarchy = $product->category->getFullHierarchy();
            $departamento = $hierarchy->where('nivel', 2)->first();

            if ($departamento) {
                return $departamento->name;
            }
        }

        return 'N/A';
    }

    /**
     * Busca produtos da biblioteca baseado no mercadológico da gôndola
     */
    private function getLibraryProducts($gondola, $gondolaProducts): array
    {
        // Obter IDs dos produtos já na gôndola para excluir
        $gondolaProductIds = collect($gondolaProducts)->pluck('id_produto')->toArray();

        // Buscar produtos da biblioteca baseado no mercadológico da gôndola (OTIMIZADO)
        // dimensões (depth/width/height) e imagem (url) são colunas diretas — só `category` é relação
        $query = Product::with([
            'category',
        ])
            ->whereNotIn('id', $gondolaProductIds) // Excluir produtos já na gôndola
            ->where('status', 'published'); // Apenas produtos publicados

        // Buscar o planograma separadamente
        $planogram = Planogram::find($gondola->planogram_id);

        // Filtrar pelo mercadológico do planograma se existir (USANDO O CAMPO category_id)
        Log::info('🔍 Verificando mercadológico do planograma:', [
            'gondola_id' => $gondola->id,
            'planogram_id' => $gondola->planogram_id,
            'planogram_category_id' => $planogram ? $planogram->category_id : 'N/A',
        ]);

        $descendants = [];

        if ($planogram && $planogram->category_id) {
            $categoryId = $planogram->category_id;

            Log::info('🔍 Categoria do planograma:', [
                'category_id' => $categoryId,
            ]);

            // Usar o mesmo endpoint que o Products.vue usa
            try {
                $response = Http::get(url('/api/categories/mercadologico'), [
                    'parent_id' => $categoryId,
                ]);

                if ($response->successful()) {
                    $childCategories = $response->json();
                    $directChildren = collect($childCategories)->pluck('id')->toArray();

                    // Buscar TODOS os descendentes (filhas, netas, bisnetas, etc.)
                    $allDescendants = $this->getAllCategoryDescendants($directChildren);
                    $allDescendants = array_merge($allDescendants, $directChildren);
                    $allDescendants[] = $categoryId; // Incluir a própria categoria do planograma
                    $allDescendants = array_unique($allDescendants); // Remover duplicatas

                    Log::info('🔍 Categorias filhas + todos descendentes (via endpoint HTTP):', [
                        'main_category_id' => $categoryId,
                        'endpoint_url' => url('/api/categories/mercadologico'),
                        'direct_children_count' => count($directChildren),
                        'all_descendants_count' => count($allDescendants),
                        'direct_children_names' => collect($childCategories)->pluck('name')->toArray(),
                    ]);

                    $query->whereIn('category_id', $allDescendants);
                    Log::info('🔍 Filtrando produtos da biblioteca por TODOS os descendentes:', [
                        'descendants_count' => count($allDescendants),
                    ]);
                } else {
                    Log::error('❌ Erro ao buscar categorias via endpoint:', [
                        'status' => $response->status(),
                        'response' => $response->body(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('❌ Erro ao fazer requisição HTTP para categorias:', [
                    'category_id' => $categoryId,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            $products = [];

            return $products;
            // Log::info('⚠️ Planograma não tem category_id definido');

            // // Log para debug - verificar quantos produtos existem no total
            // $totalProducts = \App\Models\Product::where('status', 'published')->count();
            // $totalProductsWithoutDimension = \App\Models\Product::where('status', 'published')
            //     ->whereDoesntHave('dimensions')
            //     ->count();
            // $totalProductsWithDimension = \App\Models\Product::where('status', 'published')
            //     ->whereHas('dimensions')
            //     ->count();

            // Log::info('🔍 Debug - Produtos totais no banco:', [
            //     'total_products' => $totalProducts,
            //     'products_with_dimension' => $totalProductsWithDimension,
            //     'products_without_dimension' => $totalProductsWithoutDimension
            // ]);
        }

        // Remover limit temporariamente para investigação
        $libraryProducts = $query->get();

        // Log para debug - verificar produtos com/sem dimensão (coluna direta `depth`)
        $productsWithDimension = $libraryProducts->filter(function ($product) {
            return ! is_null($product->depth);
        });
        $productsWithoutDimension = $libraryProducts->filter(function ($product) {
            return is_null($product->depth);
        });

        // Buscar produto específico para debug
        $specificProduct = Product::where('ean', '7896049901124')->first();
        if ($specificProduct) {
            Log::info('🔍 Debug - Produto específico ARROZ ROZCATO:', [
                'id' => $specificProduct->id,
                'name' => $specificProduct->name,
                'ean' => $specificProduct->ean,
                'status' => $specificProduct->status,
                'category_id' => $specificProduct->category_id,
                'has_dimensions' => ! is_null($specificProduct->depth),
                'in_gondola_ids' => in_array($specificProduct->id, $gondolaProductIds),
                'in_descendants' => in_array($specificProduct->category_id, $descendants),
            ]);
        } else {
            Log::info('❌ Produto ARROZ ROZCATO não encontrado no banco');
        }

        Log::info('🔍 Debug - Produtos da biblioteca:', [
            'total_library_products' => $libraryProducts->count(),
            'products_with_dimension' => $productsWithDimension->count(),
            'products_without_dimension' => $productsWithoutDimension->count(),
            'sample_products_without_dimension' => $productsWithoutDimension->take(5)->pluck('name')->toArray(),
        ]);

        $products = [];
        foreach ($libraryProducts as $product) {
            $products[] = [
                'nome_planograma' => $gondola->name ?? "Gôndola {$gondola->id}",
                'fluxo' => $this->determineFlowDirection($gondola),
                'modulo' => 'BIBLIOTECA', // Identificar como biblioteca
                'prateleira' => 'N/A',
                'id_produto' => $product->id,
                'ean' => $product->ean ?? '',
                'nome' => $product->name ?? 'Produto sem nome',
                'frentes' => 0, // Produtos da biblioteca não têm frentes
                'unidades_altura' => 0,
                'unidades_profundidade' => 0,
                'total_unidades' => 0, // Produtos da biblioteca não têm unidades calculadas
                // Dados adicionais para contexto
                'categoria' => $this->getProductCategoryName($product),
                'departamento' => $this->getProductDepartmentName($product),
                'codigo_erp' => $product->codigo_erp ?? '',
                'has_image' => $this->checkProductHasImage($product),
                'has_dimension' => ! is_null($product->depth),
                'image_url' => $product->image_url ?? null,
                'position_section' => 0,
                'position_shelf' => 0,
                'position_segment' => 0,
                'source' => 'library', // Identificar origem
            ];
        }

        return $products;
    }

    /**
     * Busca TODOS os descendentes de múltiplas categorias (recursivo)
     */
    private function getAllCategoryDescendants($categoryIds)
    {
        $allDescendants = [];

        foreach ($categoryIds as $categoryId) {
            $descendants = $this->getCategoryDescendants($categoryId);
            $allDescendants = array_merge($allDescendants, $descendants);
        }

        return array_unique($allDescendants);
    }

    /**
     * Método auxiliar para buscar todos os descendentes de uma categoria (recursivo)
     */
    private function getCategoryDescendants($categoryId)
    {
        $descendants = [];

        // Busca filhos diretos
        $children = Category::where('category_id', $categoryId)->get();

        foreach ($children as $child) {
            $descendants[] = $child->id;
            // Recursivamente busca descendentes dos filhos
            $childDescendants = $this->getCategoryDescendants($child->id);
            $descendants = array_merge($descendants, $childDescendants);
        }

        return $descendants;
    }
}
