<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\BorderPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function record(
        string $event,
        ?User $user = null,
        ?BorderPost $borderPost = null,
        ?Model $auditable = null,
        array $metadata = [],
        ?Request $request = null
    ): AuditEvent {
        return AuditEvent::query()->create([
            'user_id' => $user?->id,
            'border_post_id' => $borderPost?->id,
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'metadata' => $metadata ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'occurred_at' => now(),
        ]);
    }
}
