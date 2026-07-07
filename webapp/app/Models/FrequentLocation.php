<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FrequentLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'country_code',
        'country_name',
        'name',
        'admin_area',
        'district',
        'category',
        'aliases',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
