<?php

namespace App\Models;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Sale as PlannerateSale;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends PlannerateSale
{
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
