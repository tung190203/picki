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
            $typeName = $badgeTypes->has($badge->type) ? $badgeTypes->get($badge->type)->name : $badge->type;
            return [
                'id' => $badge->id, // badge id
                'user_badge_id' => $userBadge ? $userBadge->id : null,
                'code' => $badge->code,
                'name' => $badge->name,
                'description' => $badge->description,
                'icon_url' => $badge->icon_url,
                'type' => $badge->type,
                'type_name' => $typeName,
                'priority' => $badge->priority,
                'is_unlocked' => $userBadge ? true : false,
                'is_featured' => $userBadge ? (bool) $userBadge->is_featured : false,
                'acquired_at' => $userBadge ? $userBadge->acquired_at : null,
            ];
        });

        return response()->json(['data' => $result]);
    }

    public function setFeatured(Request $request)
    {
        $request->validate([
            'user_badge_ids' => 'required|array|max:3',
            'user_badge_ids.*' => 'integer|exists:user_badges,id',
        ]);

        $userId = auth()->id();

        // Verify ownership
        $owned = UserBadge::whereIn('id', $request->user_badge_ids)
            ->where('user_id', $userId)
            ->count();
            
        if ($owned !== count($request->user_badge_ids)) {
            return response()->json(['message' => 'Invalid badge selection'], 403);
        }

        // Reset all to not featured
        UserBadge::where('user_id', $userId)->update(['is_featured' => false]);

        // Set new featured
        UserBadge::whereIn('id', $request->user_badge_ids)->update(['is_featured' => true]);

        return response()->json(['message' => 'Featured badges updated successfully']);
    }
}
