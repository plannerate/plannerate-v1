<?php

namespace App\Models;

use Database\Factories\UsefulLinkFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UsefulLink extends Model
{
    /** @use HasFactory<UsefulLinkFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @var string
     */
    protected $connection = 'landlord';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'url',
        'logo',
        'description',
        'show_on_tenant_dashboard',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'show_on_tenant_dashboard' => 'boolean',
        ];
    }
}
