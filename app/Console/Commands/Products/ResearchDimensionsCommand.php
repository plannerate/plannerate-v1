<?php

namespace App\Console\Commands\Products;

use App\Enums\DimensionStatus;
use App\Models\Product;
use App\Services\ProductDimensionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('products:research-dimensions {--limit=50 : Número máximo de produtos a enfileirar} {--status=pending : Status dos produtos a pesquisar (pending|rejected|not_found)}')]
#[Description('Enfileira jobs de pesquisa de dimensões para produtos sem dados ou reprovados.')]
class ResearchDimensionsCommand extends Command
{
    public function __construct(private readonly ProductDimensionService $service)
    {
        parent::__construct();
    }

    /**
     * Enfileira jobs de pesquisa de dimensões por status, respeitando o limite informado.
     */
    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $statusValue = (string) $this->option('status');

        $allowedStatuses = [
            DimensionStatus::Pending->value,
            DimensionStatus::Rejected->value,
            DimensionStatus::NotFound->value,
        ];

        if (! in_array($statusValue, $allowedStatuses, true)) {
            $this->error("Status inválido: {$statusValue}. Use: ".implode(', ', $allowedStatuses));

            return Command::FAILURE;
        }

        $status = DimensionStatus::from($statusValue);

        $products = Product::query()
            ->where('dimension_status', $status)
            ->whereNotNull('ean')
            ->limit($limit)
            ->get(['id', 'name', 'ean']);

        if ($products->isEmpty()) {
            $this->info("Nenhum produto com status '{$statusValue}' encontrado.");

            return Command::SUCCESS;
        }

        $dispatched = 0;
        foreach ($products as $product) {
            $this->service->research($product);
            $dispatched++;
        }

        $this->info("✓ {$dispatched} produto(s) enfileirado(s) para pesquisa (status: {$statusValue}).");

        return Command::SUCCESS;
    }
}
