<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutboundWebhook extends Model
{
    protected $fillable = [
        'country_code',
        'name',
        'endpoint_url',
        'signing_secret',
        'reporting_module',
        'form_id',
        'is_active',
        'timeout_seconds',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'signing_secret' => 'encrypted',
            'is_active' => 'boolean',
            'timeout_seconds' => 'integer',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function appliesTo(MobileSubmission $submission): bool
    {
        if (! $this->is_active || $this->country_code !== $submission->country_code) {
            return false;
        }

        if ($this->reporting_module && $this->reporting_module !== $submission->reporting_module) {
            return false;
        }

        return ! $this->form_id || $this->form_id === $submission->form_id;
    }
}
