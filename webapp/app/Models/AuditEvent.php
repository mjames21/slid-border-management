<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditEvent extends Model
{
    protected $fillable = [
        'user_id',
        'border_post_id',
        'event',
        'auditable_type',
        'auditable_id',
        'metadata',
        'ip_address',
        'user_agent',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function borderPost(): BelongsTo
    {
        return $this->belongsTo(BorderPost::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
