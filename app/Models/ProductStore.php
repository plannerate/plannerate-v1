<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductStore extends Pivot
{
    protected $table = 'product_store';

    public $incrementing = false;

    protected $keyType = 'string';
}
