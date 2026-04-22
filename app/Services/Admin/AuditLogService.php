<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogService
{
    public function log(
        User $admin,
        string $action,
        string $targetType,
        ?int $targetId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $note = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $admin->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'note' => $note,
        ]);
    }
}
