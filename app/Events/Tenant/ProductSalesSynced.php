<?php

namespace App\Events\Tenant;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sinaliza que a busca sob demanda de um produto (vendas + opcionalmente dados)
 * terminou. O frontend escuta este evento no canal do tenant e, quando o
 * product_id casa com o produto em tela, recarrega a página para exibir os
 * dados recém-importados.
 *
 * É ShouldBroadcastNow porque é disparado de dentro de um Job TenantAware —
 * re-enfileirar o broadcast o executaria num worker sem tenant restaurado.
 */
class ProductSalesSynced implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $productId,
        public string $status,
        public int $products = 0,
        public int $sales = 0,
        public ?string $message = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->tenantId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'product.sales.synced';
    }

    /**
     * @return array<string, string|int|null>
     */
    public function broadcastWith(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'product_id' => $this->productId,
            'status' => $this->status,
            'products' => $this->products,
            'sales' => $this->sales,
            'message' => $this->message,
        ];
    }
}
