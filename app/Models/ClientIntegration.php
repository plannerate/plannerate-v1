<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Models;

use Callcocam\LaravelRaptor\Models\AbstractModel;
use Callcocam\LaravelRaptor\Support\Landlord\UsesLandlordConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientIntegration extends AbstractModel
{
    /** @use HasFactory<\Database\Factories\ClientIntegrationFactory> */
    use HasFactory, SoftDeletes, UsesLandlordConnection;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        static::$landlord->disable();
    }

    public function client()
    {
        return $this->hasOne(Client::class);
    }

    protected $casts = [
        'authentication_headers' => 'array',
        'authentication_body' => 'array',
        'config' => 'array',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected function slugTo()
    {
        return false;
    }
}
