<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeploymentRequest extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'country_name',
        'agency_name',
        'contact_name',
        'contact_email',
        'contact_phone',
        'contact_role',
        'deployment_plan',
        'deployment_type',
        'expected_posts',
        'expected_users',
        'modules',
        'message',
        'status',
        'source_ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'expected_posts' => 'integer',
            'expected_users' => 'integer',
            'modules' => 'array',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_NEW => 'New',
            self::STATUS_REVIEWING => 'Reviewing',
            self::STATUS_CONTACTED => 'Contacted',
            self::STATUS_CLOSED => 'Closed',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status ?: self::STATUS_NEW] ?? self::statusLabels()[self::STATUS_NEW];
    }
}
