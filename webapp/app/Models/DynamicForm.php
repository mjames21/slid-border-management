<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynamicForm extends Model
{
    public const MODULE_IMMIGRATION = 'immigration';
    public const MODULE_CUSTOMS = 'customs';
    public const MODULE_SECURITY = 'security';
    public const MODULE_HEALTH = 'health';
    public const MODULE_OTHER = 'other';

    protected $fillable = [
        'country_code',
        'reporting_module',
        'form_id',
        'title',
        'published_version_id',
        'is_template',
        'template_key',
        'template_description',
        'template_summary',
    ];

    protected $casts = [
        'is_template' => 'boolean',
        'template_summary' => 'array',
    ];

    public static function moduleLabels(): array
    {
        return [
            self::MODULE_IMMIGRATION => 'Immigration',
            self::MODULE_CUSTOMS => 'Customs',
            self::MODULE_SECURITY => 'Security / Incident',
            self::MODULE_HEALTH => 'Health / Quarantine',
            self::MODULE_OTHER => 'Other Border Service',
        ];
    }

    public static function moduleStandardReferences(): array
    {
        return [
            self::MODULE_IMMIGRATION => 'ICAO TRIP / Doc 9303 MRTD inspection baseline',
            self::MODULE_CUSTOMS => 'WCO Data Model / Single Window customs declaration baseline',
            self::MODULE_SECURITY => 'WCO SAFE / border enforcement incident and referral baseline',
            self::MODULE_HEALTH => 'WHO International Health Regulations (2005) point-of-entry screening baseline',
            self::MODULE_OTHER => 'National cross-border regulatory agency baseline',
        ];
    }

    public static function normalizeModule(?string $module): string
    {
        $module = strtolower(trim((string) $module));

        return array_key_exists($module, self::moduleLabels()) ? $module : self::MODULE_IMMIGRATION;
    }

    public static function standardReferenceForModule(?string $module): string
    {
        $module = self::normalizeModule($module);

        return self::moduleStandardReferences()[$module];
    }

    public function moduleLabel(): string
    {
        return self::moduleLabels()[$this->reporting_module ?: self::MODULE_IMMIGRATION] ?? self::moduleLabels()[self::MODULE_IMMIGRATION];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DynamicFormVersion::class);
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(DynamicFormVersion::class, 'published_version_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }
}
