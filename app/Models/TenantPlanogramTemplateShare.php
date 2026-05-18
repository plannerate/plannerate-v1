<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPlanogramTemplateShare extends Model
{
    use HasUlids;

    protected $connection = 'landlord';

    protected $fillable = [
        'global_template_id',
        'tenant_id',
        'shared_at',
        'shared_by',
    ];

    protected function casts(): array
    {
        return [
            'shared_at' => 'datetime',
        ];
    }

    public function globalTemplate(): BelongsTo
    {
        return $this->belongsTo(GlobalPlanogramTemplate::class, 'global_template_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }
}
