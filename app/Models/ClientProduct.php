<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ClientProduct extends Pivot
{
    protected $table = 'client_product';

    public $incrementing = false;

    protected $keyType = 'string';
}
