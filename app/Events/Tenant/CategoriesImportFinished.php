<?php

namespace App\Events\Tenant;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado quando o job de importação de categorias termina com sucesso (ficheiro processado).
 */
class CategoriesImportFinished implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $userId,
        public string $tenantId,
        public string $tenantSlug,
        public int $rowsProcessed,
        public int $categoriesCreated,
        public int $categoriesUpdated,
        public int $productsLinked,
        public int $warningsCount,
        public int $errorsCount,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'categories.import.finished';
    }
}
