<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditTrail
{
    public function record(string $action, Model $resource, ?string $reason = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'resource_type' => class_basename($resource),
            'resource_id' => $resource->getKey(),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
