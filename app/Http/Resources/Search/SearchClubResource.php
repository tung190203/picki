<?php

namespace App\Http\Resources\Search;

use App\Enums\ClubMembershipStatus;
use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Http\Resources\Concerns\ResolvesClubMemberCount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class SearchClubResource extends JsonResource
{
    use ResolvesClubMemberCount;

    public function toArray(Request $request): array
    {
        $userId = auth()->id();

        $isMember = false;
        $isAdmin = false;
        $hasPendingRequest = false;
        $hasInvitation = false;
        $invitedBy = null;

        if ($userId) {
            // Prefer the batch-preloaded flag from ClubService::attachMembershipStatus
            // (covers users with role=Admin even when the members relation isn't eager-loaded,
            // and avoids a per-item lookup).
            if (isset($this->is_admin)) {
                $isAdmin = (bool) $this->is_admin;
            }
            if (isset($this->is_member)) {
                $isMember = (bool) $this->is_member;
            }
        }

        if ($userId && $this->relationLoaded('members')) {
            $membership = $this->members->firstWhere('user_id', $userId);

            if ($membership) {
                $status = $membership->membership_status;
                $role = $membership->role;

                $isMember = $isMember || ($status === ClubMembershipStatus::Joined
                    && $membership->status !== ClubMemberStatus::Suspended);
                // In DB, created_by is NOT NULL unsigned int (defaults to 0 when not set).
                // Treat both null and 0 as "no creator set" → fall through to role check.
                $isAdmin = $isAdmin
                    || (($this->created_by ?? 0) !== 0 && $this->created_by === $userId)
                    || in_array($role, [ClubMemberRole::Admin->value, ClubMemberRole::Manager->value, ClubMemberRole::Secretary->value]);
                $hasPendingRequest = $status === ClubMembershipStatus::Pending
                    && $membership->invited_by === null;
                $hasInvitation = $status === ClubMembershipStatus::Pending
                    && $membership->invited_by !== null;
            }
        }

        return [
            // Existing fields
            'id'               => $this->id,
            'name'             => $this->name,
            'address'          => $this->address ?? $this->whenLoaded('profile', fn() => $this->profile?->address),
            'latitude'         => $this->latitude ?? $this->whenLoaded('profile', fn() => $this->profile?->latitude),
            'longitude'        => $this->longitude ?? $this->whenLoaded('profile', fn() => $this->profile?->longitude),
            'logo_url'         => $this->logo_url,
            'status'           => $this->status->value,
            'is_verified'      => (bool) $this->is_verified,
            'is_public'        => (bool) ($this->is_public ?? true),
            'created_by'       => $this->creator?->id,
            'quantity_members' => $this->resolveClubQuantityMembers(),
            'is_admin'         => $isAdmin,
            'is_member'        => $isMember,
            'has_pending_request' => $hasPendingRequest,
            'has_invitation'   => $hasInvitation,
            'invited_by'       => $invitedBy,
            'profile'          => $this->whenLoaded('profile', fn() => [
                'description'     => $this->profile?->description,
                'cover_image_url' => $this->profile?->cover_image_url,
            ]),
            'distance'         => $this->when(isset($this->distance), round($this->distance, 1)),
            'marker_type'      => 'club',
            'active_matches_count' => $this->active_matches_count ?? 0,
            'active_tournaments_count' => $this->active_tournaments_count ?? 0,
            'announcements_count' => $this->announcements_count ?? 0,

            // New fields for Phase 1
            'followers_count' => $this->followers_count ?? 0,
            'is_following' => $this->is_following ?? false,
            'score_range' => $this->skill_level, // { min, max } - preloaded by ClubService::attachSkillLevel()
            'score_range_text' => $this->skill_level
                ? $this->skill_level['min'] . '-' . $this->skill_level['max']
                : null,
            'recruitment_status' => $this->recruitment_status,
            'recruitment_status_text' => $this->getRecruitmentStatusText(),
            'recurring_schedule_text' => $this->recurring_schedule_text
                ? Str::limit($this->recurring_schedule_text, 100)
                : null,
            'primary_home_court' => $this->primary_home_court,
            'score_match' => $this->buildScoreMatch(), // { user_score, tolerance, delta } - chỉ có khi sub_tab=suit_level

            // New fields for Phase 2 - Admin & Total Counts
            // Admin = creator (simple format to avoid N+1 from UserResource)
            'admin' => $this->buildAdmin(),
            'total_mini_tournaments_count' => $this->total_mini_tournaments_count ?? 0,
            'total_tournaments_count' => $this->total_tournaments_count ?? 0,
        ];
    }

    /**
     * Admin object: ưu tiên creator (người tạo CLB).
     * Nếu creator đã bị xoá (orphaned FK), fallback sang member role cao nhất
     * còn active+joined để FE vẫn có người liên hệ. Ponytail: simple priority list,
     * đủ cho orphaned data; nếu cần "ai đã thật sự tạo" thì tra audit log.
     */
    private function buildAdmin(): ?array
    {
        // 1) Prefer 'leader' pre-attached bởi ClubSearchEnricher::attachLeaderInfo
        //    (DB: 1 query batch, role priority Admin>Manager>Secretary, có score).
        //    Tin tưởng enricher hơn vì nó cover cả CLB created_by=0 (orphaned FK).
        if (isset($this->leader) && is_array($this->leader) && !empty($this->leader['user_id'])) {
            $leader = $this->leader;
            return [
                'id' => (int) $leader['user_id'],
                'full_name' => $leader['full_name'] ?? null,
                'avatar_url' => $leader['avatar_url'] ?? null,
                'vndupr_score' => isset($leader['vndupr_score']) && $leader['vndupr_score'] !== null
                    ? round((float) $leader['vndupr_score'], 3)
                    : null,
            ];
        }

        // 2) Fallback: nếu enricher chưa chạy, dùng creator (nếu có)
        if ($this->creator) {
            return $this->formatAdminUser($this->creator);
        }

        // 3) Fallback cuối: member role cao nhất
        if (!$this->relationLoaded('members')) {
            return null;
        }

        $fallback = $this->members
            ->where('membership_status', ClubMembershipStatus::Joined->value)
            ->where('status', ClubMemberStatus::Active->value)
            ->sortBy(function ($m) {
                return match ($m->role) {
                    ClubMemberRole::Admin->value => 0,
                    ClubMemberRole::Manager->value => 1,
                    ClubMemberRole::Secretary->value => 2,
                    default => 99,
                };
            })
            ->first();

        if (!$fallback) {
            return null;
        }

        if (!$fallback->relationLoaded('user')) {
            return [
                'id' => $fallback->user_id,
                'full_name' => null,
                'avatar_url' => null,
                'vndupr_score' => null,
            ];
        }

        return $this->formatAdminUser($fallback->user);
    }

    /**
     * Chuẩn hoá shape admin + lấy vndupr_score max từ relation đã preload
     * (fallback query nếu controller chưa load), làm tròn 3 chữ số thập phân.
     */
    private function formatAdminUser(User $user): array
    {
        $score = $this->relationLoaded('creator') || $user->relationLoaded('vnduprScores')
            ? ($user->vnduprScores->max('score_value'))
            : $user->vnduprScores()->max('score_value');

        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'avatar_url' => $user->avatar_url,
            'vndupr_score' => $score !== null ? round((float) $score, 3) : null,
        ];
    }

    private function getRecruitmentStatusText(): ?string
    {
        return match ($this->recruitment_status) {
            'open' => 'Đang tuyển thành viên',
            'closed' => 'Đã đóng tuyển',
            'invite_only' => 'Chỉ mời',
            default => null,
        };
    }

    private function buildScoreMatch(): ?array
    {
        // Chỉ attach khi sub_tab=suit_level (enricher sẽ set user_vndupr_score)
        if (!isset($this->user_vndupr_score)) {
            return null;
        }

        return [
            'user_score' => (float) $this->user_vndupr_score,
            'tolerance' => 0.5,
            'delta' => $this->score_match_score !== null
                ? round((float) $this->score_match_score, 2)
                : null,
        ];
    }
}
