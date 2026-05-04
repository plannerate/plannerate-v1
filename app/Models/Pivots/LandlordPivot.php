<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

class LandlordPivot extends MorphPivot
{
    /**
     * Get the current connection name for the model.
     */
    public function getConnectionName(): ?string
    {
        return 'landlord';
    }
}
