<?php

namespace App\Services\Admin\AdminPushNotification\Resolvers;

use App\Enums\ClubMembershipStatus;
use App\Enums\ClubMemberStatus;
use App\Enums\AdminPushNotification\RecipientType;
use App\Models\AdminPushNotificationCampaign;
use App\Models\Club\Club;
use App\Models\User;
use App\Services\Admin\AdminPushNotification\PushNotificationRecipientResolver;
use App\Services\Admin\AdminPushNotification\RecipientQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ClubMembersResolver implements PushNotificationRecipientResolver
{
    public function buildQuery(array $config): Builder
    {
        $clubId = (int) ($config['club_id'] ?? 0);

        $query = User::query()
            ->join('club_members', 'club_members.user_id', '=', 'users.id')
            ->where('club_members.club_id', $clubId)
            ->whereNull('club_members.deleted_at')
            ->where('club_members.membership_status', ClubMembershipStatus::Joined->value)
            ->where('club_members.status', ClubMemberStatus::Active->value)
            ->select('users.*');

        return RecipientQueryBuilder::applyCommonFilters($query);
    }

    public function label(array $config): string
    {
        $clubId = (int) ($config['club_id'] ?? 0);
        $club = Club::find($clubId);

        if (!$club) {
            return 'CLB không tồn tại';
        }

        $memberCount = DB::table('club_members')
            ->where('club_id', $clubId)
            ->whereNull('deleted_at')
            ->where('membership_status', ClubMembershipStatus::Joined->value)
            ->where('status', ClubMemberStatus::Active->value)
            ->count();

        return sprintf('%s - %d thành viên', $club->name, $memberCount);
    }

    public function warnings(array $config): array
    {
        return [
            'Chỉ gửi cho thành viên đang hoạt động (joined + active) của câu lạc bộ.',
        ];
    }

    public static function makeFor(AdminPushNotificationCampaign $campaign): self
    {
        return new self();
    }
}