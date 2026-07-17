<?php

namespace App\Policies;

use App\Models\ScoreVerificationRequest;
use App\Models\User;

class ScoreVerificationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ScoreVerificationRequest $request): bool
    {
        return $user->id === $request->user_id || $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function approve(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function reject(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
