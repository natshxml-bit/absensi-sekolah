<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogService
{
    public function record(
        ?User $user,
        string $action,
        ?string $description = null,
        ?Model $auditable = null,
        ?Request $request = null,
    ): ActivityLog {
        $request ??= request();

        return ActivityLog::create([
            'user_id' => $user?->id,
            'role' => $user?->role,
            'action' => $action,
            'description' => $description,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'auditable_type' => $auditable ? $auditable->getMorphClass() : null,
            'auditable_id' => $auditable ? $auditable->getKey() : null,
        ]);
    }
}