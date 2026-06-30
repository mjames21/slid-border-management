<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardView extends Model
{
    protected $fillable = [
        'user_id',
        'country_code',
        'name',
        'description',
        'time_window_hours',
        'filters',
        'layout',
        'is_default',
    ];

    protected $casts = [
        'time_window_hours' => 'integer',
        'filters' => 'array',
        'layout' => 'array',
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }
}
