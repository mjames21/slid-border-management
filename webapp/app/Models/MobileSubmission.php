<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MobileSubmission extends Model
{
    protected $fillable = [
        'server_uid',
        'user_id', 'mobile_device_id', 'border_post_id', 'country_code', 'border_post_code', 'border_post_digital_address', 'region',
        'reporting_module',
        'device_latitude', 'device_longitude', 'device_location_accuracy_meters', 'device_location_captured_at',
        'device_id', 'local_id', 'form_id', 'form_version', 'answers',
        'client_created_at', 'client_updated_at', 'client_synced_at', 'received_at', 'status', 'rejection_reason',
        'source_ip', 'user_agent',
    ];

    protected $casts = [
        'answers' => 'array',
        'client_created_at' => 'datetime',
        'client_updated_at' => 'datetime',
        'client_synced_at' => 'datetime',
        'received_at' => 'datetime',
        'device_latitude' => 'decimal:7',
        'device_longitude' => 'decimal:7',
        'device_location_accuracy_meters' => 'decimal:2',
        'device_location_captured_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mobileDevice(): BelongsTo
    {
        return $this->belongsTo(MobileDevice::class);
    }

    public function borderPost(): BelongsTo
    {
        return $this->belongsTo(BorderPost::class);
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function reportingModuleLabel(): string
    {
        return DynamicForm::moduleLabels()[$this->reporting_module ?: DynamicForm::MODULE_IMMIGRATION]
            ?? DynamicForm::moduleLabels()[DynamicForm::MODULE_IMMIGRATION];
    }
}
