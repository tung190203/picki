<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserBadge;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    public function getUserBadges(int $userId)
    {
        $allBadges = \App\Models\Badge::where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        $userBadges = UserBadge::where('user_id', $userId)
            ->get()
            ->keyBy('badge_id');

        $badgeTypes = \App\Models\BadgeType::all()->keyBy('code');

        $result = $allBadges->map(function ($badge) use ($userBadges, $badgeTypes) {
            $userBadge = $userBadges->get($badge->id);
            $typeModel = $badgeTypes->has($badge->type) ? $badgeTypes->get($badge->type) : null;
            $typeName = $typeModel ? $typeModel->name : $badge->type;
            $typeDescription = $typeModel ? $typeModel->description : null;
            return [
                'id' => $badge->id, // badge id
                'user_badge_id' => $userBadge ? $userBadge->id : null,
                'code' => $badge->code,
                'name' => $badge->name,
                'description' => $badge->description,
                'icon_url' => $badge->icon_url,
                'type' => $badge->type,
                'type_name' => $typeName,
                'type_description' => $typeDescription,
                'priority' => $badge->priority,
                'is_unlocked' => $userBadge ? true : false,
                'is_featured' => $userBadge ? (bool) $userBadge->is_featured : false,
                'is_avatar' => $userBadge ? (bool) $userBadge->is_avatar : false,
                'is_primary' => $userBadge ? (bool) $userBadge->is_avatar : false,
                'featured_order' => ($userBadge && $userBadge->is_featured)
                    ? ($userBadge->updated_at ? $userBadge->updated_at->getTimestamp() : (int) $badge->id)
                    : null,
                'acquired_at' => $userBadge ? $userBadge->acquired_at : null,
            ];
        });

        return response()->json(['data' => $result]);
    }

    public function setFeatured(Request $request)
    {
        $request->validate([
            'user_badge_ids' => 'present|array',
            'user_badge_ids.*' => 'integer|exists:user_badges,id',
            'avatar_user_badge_id' => 'nullable|integer|exists:user_badges,id',
        ]);

        $userId = auth()->id();

        // Verify ownership for featured badges
        $owned = UserBadge::whereIn('id', $request->user_badge_ids)
            ->where('user_id', $userId)
            ->count();
            
        if ($owned !== count($request->user_badge_ids)) {
            return response()->json(['message' => 'Invalid badge selection'], 403);
        }

        // Reset all to not featured
        UserBadge::where('user_id', $userId)->update(['is_featured' => false]);

        // Set new featured and preserve exact slot order (1 -> 2 -> 3) via updated_at
        $baseTime = now();
        foreach (array_values($request->user_badge_ids) as $index => $userBadgeId) {
            UserBadge::where('id', $userBadgeId)
                ->where('user_id', $userId)
                ->update([
                    'is_featured' => true,
                    'updated_at' => $baseTime->copy()->addSeconds($index),
                ]);
        }

        // If avatar_user_badge_id is provided in the same request, update it as well
        if ($request->has('avatar_user_badge_id')) {
            $avatarId = $request->input('avatar_user_badge_id');
            if ($avatarId !== null) {
                $ownedAvatar = UserBadge::where('id', $avatarId)->where('user_id', $userId)->exists();
                if (!$ownedAvatar) {
                    return response()->json(['message' => 'Invalid avatar badge selection'], 403);
                }
            }
            app(\App\Services\BadgeService::class)->setAvatarBadge($userId, $avatarId);
        }

        return response()->json(['message' => 'Featured badges updated successfully']);
    }

    public function setAvatar(Request $request)
    {
        $request->validate([
            'user_badge_id' => 'nullable|integer|exists:user_badges,id',
        ]);

        $userId = auth()->id();
        $userBadgeId = $request->input('user_badge_id');

        if ($userBadgeId !== null) {
            $owned = UserBadge::where('id', $userBadgeId)
                ->where('user_id', $userId)
                ->exists();

            if (!$owned) {
                return response()->json(['message' => 'Invalid badge selection'], 403);
            }
        }

        app(\App\Services\BadgeService::class)->setAvatarBadge($userId, $userBadgeId);

        return response()->json([
            'message' => $userBadgeId
                ? 'Avatar badge updated successfully'
                : 'Avatar badge removed successfully',
        ]);
    }
}