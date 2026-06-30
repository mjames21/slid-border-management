<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'outbound_webhook_id',
        'mobile_submission_id',
        'event_type',
        'status',
        'attempts',
        'last_status_code',
        'payload_sha256',
        'response_body',
        'error_message',
        'delivered_at',
        'last_attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'last_status_code' => 'integer',
            'delivered_at' => 'datetime',
            'last_attempted_at' => 'datetime',
        ];
    }

    public function outboundWebhook(): BelongsTo
    {
        return $this->belongsTo(OutboundWebhook::class);
    }

    public function mobileSubmission(): BelongsTo
    {
        return $this->belongsTo(MobileSubmission::class);
    }
}
