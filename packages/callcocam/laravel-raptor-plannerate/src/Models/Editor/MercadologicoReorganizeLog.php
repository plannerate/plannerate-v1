<?php

namespace Callcocam\LaravelRaptorPlannerate\Models\Editor;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MercadologicoReorganizeLog extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'mercadologico_reorganize_logs';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_backup' => 'array',
            'agent_response' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function isSuggestion(): bool
    {
        return $this->status === 'suggestion';
    }

    public function isApplied(): bool
    {
        return $this->status === 'applied';
    }
}
