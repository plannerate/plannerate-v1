<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class IntegrationSyncDay extends Model
{
    use HasUlids;

    /**
     * @var string
     */
    protected $connection = 'landlord';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_integration_id',
        'resource',
        'reference_date',
        'status',
        'attempts',
        'error_message',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reference_date' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(TenantIntegration::class, 'tenant_integration_id');
    }

    public function markRunning(): void
    {
        $this->forceFill([
            'status' => 'running',
            'started_at' => Carbon::now(),
            'error_message' => null,
        ])->save();
    }

    public function markSuccess(): void
    {
        $this->forceFill([
            'status' => 'success',
            'finished_at' => Carbon::now(),
            'error_message' => null,
        ])->save();
    }

    public function markFailed(string $message): void
    {
        $this->forceFill([
            'status' => 'failed',
            'finished_at' => Carbon::now(),
            'error_message' => mb_substr($message, 0, 65535),
            'attempts' => $this->attempts + 1,
        ])->save();
    }
}
