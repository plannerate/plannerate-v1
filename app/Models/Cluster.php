<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Models;

use Callcocam\LaravelRaptor\Models\AbstractModel;
use Callcocam\LaravelRaptor\Support\Landlord\UsesLandlordConnection;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cluster extends AbstractModel
{
    use SoftDeletes, UsesLandlordConnection;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        static::$landlord->enable();
    }

    protected $fillable = [
        'client_id',
        'store_id',
        'tenant_id',
        'user_id',
        'name',
        'epcification_1',
        'epcification_2',
        'epcification_3',
        'slug',
        'status',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime:d/m/Y H:i:s',
        'updated_at' => 'datetime:d/m/Y H:i:s',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
