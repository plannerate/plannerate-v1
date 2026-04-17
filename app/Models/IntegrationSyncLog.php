<?php

namespace App\Models;

use Callcocam\LaravelRaptor\Models\AbstractModel;
use Callcocam\LaravelRaptor\Support\Landlord\UsesLandlordConnection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationSyncLog extends AbstractModel
{
    use UsesLandlordConnection;

    /**
     * Desabilita tenant scoping automático
     * Este modelo usa client_id para escopo, não tenant_id
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Desabilita o landlord para este modelo
        static::$landlord->disable();
    }

    protected $fillable = [
        'client_id',
        'store_id',
        'integration_type',
        'sync_type', // sales, products, purchases
        'sync_date',
        'status',
        'retry_count',
        'consecutive_failures',
        'total_items',
        'error_message',
        'error_details',
        'last_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'sync_date' => 'date',
            'error_details' => 'array',
            'last_attempt_at' => 'datetime',
        ];
    }

    public function slugTo(): bool|string
    {
        return false; // Não precisa de slug
    }

    // Relações
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    // Helpers
    public function canRetry(int $maxRetries = 5): bool
    {
        return $this->retry_count < $maxRetries && $this->status === 'failed';
    }

    public function shouldSkip(int $maxRetries = 5): bool
    {
        return $this->retry_count >= $maxRetries && $this->status === 'failed';
    }

    public function markAsSuccess(int $totalItems): self
    {
        $this->update([
            'status' => 'success',
            'retry_count' => 0,
            'total_items' => $totalItems,
            'error_message' => null,
            'error_details' => null,
            'last_attempt_at' => now(),
        ]);

        return $this;
    }

    public function markAsFailed(string $errorMessage, ?array $errorDetails = null): self
    {
        $this->increment('retry_count');
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'error_details' => $errorDetails,
            'last_attempt_at' => now(),
        ]);

        return $this;
    }

    public function markAsSkipped(): self
    {
        $this->update([
            'status' => 'skipped',
            'last_attempt_at' => now(),
        ]);

        return $this;
    }
}
