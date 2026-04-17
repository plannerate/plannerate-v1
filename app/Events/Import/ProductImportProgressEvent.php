<?php

namespace App\Events\Import;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de progresso da importação de produtos
 * 
 * Notifica o usuário sobre o progresso da importação via Reverb
 */
class ProductImportProgressEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public ?string $userId,
        public string $tenantId,
        public ?string $clientId = null,
        public string $type, // 'started', 'progress', 'completed', 'failed'
        public string $sheetName,
        public ?int $totalRows = null,
        public ?int $processedRows = null,
        public ?string $message = null,
        public ?array $stats = null, // Estatísticas da importação
    ) {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): PrivateChannel
    {
        // Usa o mesmo canal que o frontend espera para sync
        // Se tem userId, envia para canal do usuário
        // Senão, envia para canal global do tenant
        $channel = $this->userId 
            ? "import.user.{$this->userId}"
            : "import.tenant.{$this->tenantId}";
            
        return new PrivateChannel($channel);
    }

    /**
     * Nome do evento no frontend
     */
    public function broadcastAs(): string
    {
        // Usa o mesmo nome de evento que o frontend espera
        return 'import.progress';
    }

    /**
     * Dados transmitidos
     */
    public function broadcastWith(): array
    {
        // Limita o tamanho do payload para evitar erro "Payload too large"
        // Envia apenas contagens ao invés de arrays completos de warnings/errors
        $limitedStats = null;
        if ($this->stats) {
            $limitedStats = [
                'products_created' => $this->stats['products_created'] ?? 0,
                'products_updated' => $this->stats['products_updated'] ?? 0,
                'products_with_dimensions' => $this->stats['products_with_dimensions'] ?? 0,
                'products_with_additional_data' => $this->stats['products_with_additional_data'] ?? 0,
                'errors_count' => count($this->stats['errors'] ?? []),
                'warnings_count' => count($this->stats['warnings'] ?? []),
            ];
            
            // Envia apenas os primeiros 5 erros (se houver) para debug
            if (!empty($this->stats['errors'])) {
                $limitedStats['errors_sample'] = array_slice($this->stats['errors'], 0, 5);
            }
        }
        
        return [
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'client_id' => $this->clientId,
            'type' => $this->type,
            'sheet_name' => $this->sheetName,
            'total_rows' => $this->totalRows,
            'processed_rows' => $this->processedRows,
            'message' => $this->message,
            'stats' => $limitedStats,
            'timestamp' => now()->toISOString(),
        ];
    }
}

