<?php

namespace App\Models;

use Callcocam\LaravelRaptor\Models\AbstractModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends AbstractModel
{
    use HasFactory;
    use SoftDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        static::$landlord->enable();
    }
    /**
     * Os atributos que são atribuíveis em massa.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];

    /**
     * Os atributos que devem ser convertidos.
     *
     * @var array
     */
    protected $casts = [
        'code' => 'string',
        'name' => 'string',
        'description' => 'string',
        'cnpj' => 'string',
        'status' => 'string',
    ];

    protected $with = ['address'];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_provider', 'provider_id', 'product_id')
            ->withTimestamps()
            ->withPivot('codigo_erp', 'principal');
    }

    public function address()
    {
        return $this->morphOne(Address::class, 'addressable');
    }
    /**
     * Define o atributo customizado para o slug.
     *
     * @return string|bool
     */
    public function slugTo()
    {
        return 'name';
    }
}
