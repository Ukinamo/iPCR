<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditLogger
{
    public static function log(?int $userId, string $action, ?Model $subject = null, ?array $properties = null, ?Request $request = null): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties,
            'ip_address' => $request?->ip(),
        ]);
    }
}
