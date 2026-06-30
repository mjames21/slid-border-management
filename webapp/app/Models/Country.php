<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    public const TENANT_STATUS_PROSPECT = 'prospect';

    public const TENANT_STATUS_IMPLEMENTATION = 'implementation';

    public const TENANT_STATUS_ACTIVE = 'active';

    public const TENANT_STATUS_PAUSED = 'paused';

    public const PLAN_EVALUATION = 'evaluation';

    public const PLAN_PROGRAM = 'program';

    public const PLAN_NATIONAL = 'national';

    public const PLAN_DEDICATED = 'dedicated';

    public const DEPLOYMENT_HOSTED = 'hosted_workspace';

    public const DEPLOYMENT_PRIVATE_CLOUD = 'private_cloud';

    public const DEPLOYMENT_ON_PREMISE = 'on_premise';

    public const DEPLOYMENT_HYBRID = 'hybrid';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'tenant_slug',
        'tenant_status',
        'deployment_plan',
        'deployment_type',
        'support_tier',
        'data_region',
        'primary_domain',
        'immigration_agency',
        'app_title',
        'app_subtitle',
        'logo_path',
        'logo_mime_type',
        'boundary_geojson_path',
        'boundary_source_name',
        'boundary_source_type',
        'boundary_imported_at',
        'timezone',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'boundary_imported_at' => 'datetime',
        ];
    }

    public static function tenantStatusLabels(): array
    {
        return [
            self::TENANT_STATUS_PROSPECT => 'Prospect',
            self::TENANT_STATUS_IMPLEMENTATION => 'Implementation',
            self::TENANT_STATUS_ACTIVE => 'Active',
            self::TENANT_STATUS_PAUSED => 'Paused',
        ];
    }

    public static function deploymentPlanLabels(): array
    {
        return [
            self::PLAN_EVALUATION => 'Evaluation',
            self::PLAN_PROGRAM => 'Program',
            self::PLAN_NATIONAL => 'National platform',
            self::PLAN_DEDICATED => 'Dedicated environment',
        ];
    }

    public static function deploymentTypeLabels(): array
    {
        return [
            self::DEPLOYMENT_HOSTED => 'Managed hosted workspace',
            self::DEPLOYMENT_PRIVATE_CLOUD => 'Private cloud',
            self::DEPLOYMENT_ON_PREMISE => 'On-premise',
            self::DEPLOYMENT_HYBRID => 'Hybrid',
        ];
    }

    public function tenantStatusLabel(): string
    {
        return self::tenantStatusLabels()[$this->tenant_status ?: self::TENANT_STATUS_IMPLEMENTATION]
            ?? self::tenantStatusLabels()[self::TENANT_STATUS_IMPLEMENTATION];
    }

    public function deploymentPlanLabel(): string
    {
        return self::deploymentPlanLabels()[$this->deployment_plan ?: self::PLAN_PROGRAM]
            ?? self::deploymentPlanLabels()[self::PLAN_PROGRAM];
    }

    public function deploymentTypeLabel(): string
    {
        return self::deploymentTypeLabels()[$this->deployment_type ?: self::DEPLOYMENT_HOSTED]
            ?? self::deploymentTypeLabels()[self::DEPLOYMENT_HOSTED];
    }

    public function borderPosts(): HasMany
    {
        return $this->hasMany(BorderPost::class, 'country_code', 'code');
    }

    public function outboundWebhooks(): HasMany
    {
        return $this->hasMany(OutboundWebhook::class, 'country_code', 'code');
    }
}
