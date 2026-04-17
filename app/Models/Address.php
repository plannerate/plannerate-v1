<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Models;

use Callcocam\LaravelRaptor\Models\Address as ModelsAddress;
use Callcocam\LaravelRaptor\Support\Landlord\UsesLandlordConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Address extends ModelsAddress
{
    /** @use HasFactory<\Database\Factories\AddressFactory> */
    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes, UsesLandlordConnection;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        static::$landlord->enable();
    }
}
