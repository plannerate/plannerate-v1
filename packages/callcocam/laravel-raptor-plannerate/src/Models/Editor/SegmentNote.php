<?php

namespace Callcocam\LaravelRaptorPlannerate\Models\Editor;

use App\Models\Traits\BelongsToTenant;
use App\Models\User;
use Callcocam\LaravelRaptorPlannerate\Models\Traits\UsesPlannerateTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SegmentNote extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesPlannerateTenantConnection;

    protected $fillable = [
        'tenant_id',
        'gondola_id',
        'segment_id',
        'user_id',
        'content',
    ];

    public function segment()
    {
        return $this->belongsTo(Segment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
