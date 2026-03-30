<?php

namespace App\Services\Club;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMembershipStatus;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubStatus;
use App\Jobs\SendPushJob;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\Club\ClubProfile;
use App\Models\User;
use App\Notifications\ClubDissolvedNotification;
use App\Notifications\ClubRenamedNotification;
use App\Services\ImageOptimizationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ClubService
{
    public function __construct(
        protected ImageOptimizationService $imageService,
        protected ClubMemberService $memberService,
        protected ClubWalletService $walletService
    ) {
    }

    public function createClub(array $data, int $userId): Club
    {
        return DB::transaction(function () use ($data, $userId) {
            $logoPath = null;
            if (isset($data['logo_url']) && $data['logo_url'] instanceof UploadedFile) {
                $logoPath = $this->imageService->optimize($data['logo_url'], 'logos');
            }

            $coverPath = null;
            if (isset($data['cover_image_url']) && $data['cover_image_url'] instanceof UploadedFile) {
                $coverPath = $this->imageService->optimize($data['cover_image_url'], 'covers');
            }

            $status = $data['status'] ?? ClubStatus::Active->value;
            $isPublic = $data['is_public'] ?? true;

            $club = Club::create([
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'logo_url' => $logoPath,
                'status' => $status,
                'is_public' => $isPublic,
                'created_by' => $userId,
            ]);

            ClubProfile::create([
                'club_id' => $club->id,
                'cover_image_url' => $coverPath,
                'description' => $data['description'] ?? null,
            ]);

            ClubMember::create([
                'club_id' => $club->id,
                'user_id' => $userId,
                'role' => ClubMemberRole::Admin,
                'membership_status' => ClubMembershipStatus::Joined,
                'status' => ClubMemberStatus::Active,
                'joined_at' => now(),
            ]);

            $this->walletService->createWallet($club, [
                'currency' => 'VND',
            ]);

            $club->load([
                'members.user' => function ($query) {
                    $query->with(User::FULL_RELATIONS);
                },
                'profile',
                'creator'
            ]);

            return $club;
        });
    }

    public function updateClub(Club $club, array $data, int $userId): Club
    {
        if (!$club->canManage($userId)) {
            throw new \Exception('Chỉ admin/manager/secretary mới có quyền cập nhật CLB');
        }

        if (array_key_exists('footer', $data) && !$club->canEditFooter($userId)) {
            throw new \Exception('Chỉ admin và thư ký mới có quyền sửa thông tin footer');
        }

        if (isset($data['qr_code_enabled']) && $data['qr_code_enabled']) {
            $hasNewImage = isset($data['qr_code_image_url']) && $data['qr_code_image_url'] instanceof UploadedFile;
            $hasExistingImage = $club->profile && $club->profile->qr_code_image_url;

            if (!$hasNewImage && !$hasExistingImage) {
                throw new \Exception('Vui lòng tải lên ảnh QR code khi bật tính năng này');
            }
        }

        return DB::transaction(function () use ($club, $data, $userId) {
            $oldName = $club->name;

            $logoPath = $club->getRawOriginal('logo_url');
            if (isset($data['logo_url']) && $data['logo_url'] instanceof UploadedFile) {
                if ($logoPath) {
                    $this->deleteImages($logoPath);
                }
                $logoPath = $this->imageService->optimize($data['logo_url'], 'logos');
            }

            $club->update([
                'name' => $data['name'] ?? $club->name,
                'address' => $data['address'] ?? $club->address,
                'latitude' => $data['latitude'] ?? $club->latitude,
                'longitude' => $data['longitude'] ?? $club->longitude,
                'logo_url' => $logoPath,
                'status' => $data['status'] ?? $club->status,
                'is_public' => $data['is_public'] ?? $club->is_public,
            ]);

            $profile = $club->profile;

            if (isset($data['cover_image_url']) && $data['cover_image_url'] instanceof UploadedFile) {
                if ($profile && $profile->getRawCoverImagePath()) {
                    $this->deleteImages($profile->getRawCoverImagePath());
                }
                $coverPath = $this->imageService->optimize($data['cover_image_url'], 'covers');

                if ($profile) {
                    $profile->update(['cover_image_url' => $coverPath]);
                } else {
                    $profile = ClubProfile::create([
                        'club_id' => $club->id,
                        'cover_image_url' => $coverPath,
                    ]);
                }
            }

            if (isset($data['qr_code_image_url']) && $data['qr_code_image_url'] instanceof UploadedFile) {
                if (!$profile) {
                    $profile = $club->profile;
                }

                if ($profile && $profile->getRawQrCodeImagePath()) {
                    $this->deleteImages($profile->getRawQrCodeImagePath());
                }

                $qrCodePath = $this->imageService->optimizeThumbnail($data['qr_code_image_url'], 'qr_codes', 90);

                if ($profile) {
                    $profile->update(['qr_code_image_url' => $qrCodePath]);
                } else {
                    $profile = ClubProfile::create([
                        'club_id' => $club->id,
                        'qr_code_image_url' => $qrCodePath,
                    ]);
                }
            }

            if (isset($data['qr_zalo']) && $data['qr_zalo'] instanceof UploadedFile) {
                if (!$profile) {
                    $profile = $club->profile;
                }

                if ($profile && $profile->getRawQrZaloPath()) {
                    $this->deleteImages($profile->getRawQrZaloPath());
                }

                $qrZaloPath = $this->imageService->optimizeThumbnail($data['qr_zalo'], 'zalo_qr', 90);

                if ($profile) {
                    $profile->update(['qr_zalo' => $qrZaloPath]);
                } else {
                    $profile = ClubProfile::create([
                        'club_id' => $club->id,
                        'qr_zalo' => $qrZaloPath,
                    ]);
                }
            }

            if (!empty($data['remove_qr_zalo'])) {
                if (!$profile) {
                    $profile = $club->profile;
                }

                if ($profile && $profile->getRawQrZaloPath()) {
                    $this->deleteImages($profile->getRawQrZaloPath());
                }

                if ($profile) {
                    $settings = $profile->settings;
                    $settings = is_array($settings) ? $settings : [];
                    $settings['qr_zalo_enabled'] = false;

                    $profile->update([
                        'qr_zalo' => null,
                        'settings' => $settings,
                    ]);
                }
            }

            $profileFields = ['description', 'phone', 'email', 'website', 'city', 'province', 'country', 'footer', 'zalo_link', 'zalo_link_enabled', 'qr_zalo_enabled', 'qr_code_enabled'];
            if (collect($profileFields)->some(fn($field) => isset($data[$field]))) {
                if (!$profile) {
                    $profile = $club->profile;
                }

                $socialLinks = $profile && is_array($profile->social_links) ? $profile->social_links : [];
                $settings = $profile && is_array($profile->settings) ? $profile->settings : [];

                if (isset($data['qr_code_enabled'])) {
                    $settings['qr_code_enabled'] = (bool) $data['qr_code_enabled'];
                }
                if (isset($data['zalo_link_enabled'])) {
                    $settings['zalo_link_enabled'] = (bool) $data['zalo_link_enabled'];
                }
                if (isset($data['qr_zalo_enabled'])) {
                    $settings['qr_zalo_enabled'] = (bool) $data['qr_zalo_enabled'];
                }

                if (empty($socialLinks)) {
                    $socialLinks = (object) [];
                }
                if (empty($settings)) {
                    $settings = (object) [];
                }

                $profileUpdate = [
                    'description' => $data['description'] ?? $profile?->description ?? null,
                    'phone' => $data['phone'] ?? $profile?->phone ?? null,
                    'email' => $data['email'] ?? $profile?->email ?? null,
                    'website' => $data['website'] ?? $profile?->website ?? null,
                    'city' => $data['city'] ?? $profile?->city ?? null,
                    'province' => $data['province'] ?? $profile?->province ?? null,
                    'country' => $data['country'] ?? $profile?->country ?? null,
                    'footer' => array_key_exists('footer', $data) ? ($data['footer'] ?: null) : ($profile?->footer ?? null),
                    'social_links' => $socialLinks,
                    'settings' => $settings,
                ];

                if (array_key_exists('zalo_link', $data)) {
                    $profileUpdate['zalo_link'] = $data['zalo_link'] ?: null;
                }

                if ($profile) {
                    $profile->update($profileUpdate);
                } else {
                    $profileUpdate['club_id'] = $club->id;
                    $profileUpdate['zalo_link'] = array_key_exists('zalo_link', $data) ? ($data['zalo_link'] ?: null) : null;
                    $profile = ClubProfile::create($profileUpdate);
                }
            }

            $newName = $club->name;
            if (isset($data['name']) && $oldName !== $newName) {
                $message = "CLB {$oldName} đã được quản trị viên đổi tên thành {$newName}";
                $club->activeMembers()->with('user')->each(function (ClubMember $member) use ($club, $oldName, $newName, $message) {
                    $user = $member->user;
                    if ($user) {
                        $user->notify(new ClubRenamedNotification($club, $oldName, $newName));
                        SendPushJob::dispatch($user->id, 'CLB đã đổi tên', $message, [
                            'type' => 'CLUB_RENAMED',
                            'club_id' => (string) $club->id,
                        ]);
                    }
                });
            }

            $club->refresh()->load([
                'members.user' => function ($query) {
                    $query->with(User::FULL_RELATIONS);
                },
                'profile',
                'creator'
            ]);

            return $club;
        });
    }

    public function deleteClub(Club $club, int $userId): void
    {
        if (!$club->canManage($userId)) {
            throw new \Exception('Chỉ admin/manager/secretary mới có quyền xóa CLB');
        }

        DB::transaction(function () use ($club, $userId) {
            $club->activeMembers()->with('user')->each(function (ClubMember $member) use ($club, $userId) {
                if ($member->user_id === $userId) {
                    return;
                }
                $user = $member->user;
                if ($user) {
                    $user->notify(new ClubDissolvedNotification($club));
                    SendPushJob::dispatch($user->id, 'CLB đã giải tán', "CLB {$club->name} đã bị giải tán", [
                        'type' => 'CLUB_DISSOLVED',
                        'club_id' => (string) $club->id,
                    ]);
                }
            });

            $logoPath = $club->getRawOriginal('logo_url');
            if ($logoPath) {
                $this->deleteImages($logoPath);
            }

            if ($club->profile) {
                $coverPath = $club->profile->getRawCoverImagePath();
                if ($coverPath) {
                    $this->deleteImages($coverPath);
                }
            }

            $club->delete();
        });
    }

    public function restoreClub(Club $club, int $userId): Club
    {
        $isCreator = $club->created_by === $userId;
        $isSystemAdmin = User::isAdmin($userId);

        if (!$isCreator && !$isSystemAdmin) {
            throw new \Exception('Chỉ người tạo CLB hoặc admin hệ thống mới có quyền khôi phục CLB');
        }

        $club->restore();
        $club->refresh()->load([
            'members.user' => function ($query) {
                $query->with(User::FULL_RELATIONS);
            },
            'profile',
            'creator'
        ]);

        return $club;
    }

    public function getClubDetail(Club $club, ?int $userId): Club
    {
        $isMember = $userId && $club->members()
            ->where('user_id', $userId)
            ->where('membership_status', ClubMembershipStatus::Joined)
            ->where('status', ClubMemberStatus::Active)
            ->exists();

        if (!$club->is_public) {
            if (!$userId) {
                throw new \Exception('CLB này là riêng tư. Bạn cần đăng nhập để xem');
            }
            if (!$isMember) {
                throw new \Exception('Bạn không có quyền xem CLB riêng tư này');
            }
        }

        if ($isMember) {
            $members = $club->joinedMembers()
                ->with(['user' => fn ($q) => $q->with(['sports', 'sports.sport', 'sports.scores'])])
                ->get();
            $club->setRelation('members', $members);
        }

        // Load membership của user hiện tại để lấy invited_by
        if ($userId) {
            $membership = $club->members()
                ->where('user_id', $userId)
                ->with('invitedBy')
                ->first();
            $club->currentUserMembership = $membership;
        }

        return $club;
    }

    public function searchClubs(array $filters, ?int $userId): LengthAwarePaginator
    {
        $query = Club::withListRelations()->orderBy('created_at', 'desc');

        if ($userId) {
            $query->where(function ($q) use ($userId) {
                $q->where('is_public', true)
                    ->orWhereHas('members', function ($memberQuery) use ($userId) {
                        $memberQuery->where('user_id', $userId)
                            ->where('membership_status', ClubMembershipStatus::Joined)
                            ->where('status', ClubMemberStatus::Active);
                    });
            });
        } else {
            $query->where('is_public', true);
        }

        if (!empty($filters['name'])) {
            $query->search(['name'], $filters['name']);
        }

        if (!empty($filters['address'])) {
            $query->search(['address'], $filters['address']);
        }

        $hasFilter = !empty($filters['name']) || !empty($filters['address']);

        if (
            !$hasFilter &&
            (!empty($filters['minLat']) ||
                !empty($filters['maxLat']) ||
                !empty($filters['minLng']) ||
                !empty($filters['maxLng']))
        ) {
            $query->inBounds(
                $filters['minLat'],
                $filters['maxLat'],
                $filters['minLng'],
                $filters['maxLng']
            );
        }

        if (!empty($filters['lat']) && !empty($filters['lng'])) {
            $query->orderByDistance($filters['lat'], $filters['lng']);
        }

        if (!empty($filters['lat']) && !empty($filters['lng']) && !empty($filters['radius'])) {
            $query->nearBy($filters['lat'], $filters['lng'], $filters['radius']);
        }

        $perPage = $filters['per_page'] ?? $filters['perPage'] ?? Club::PER_PAGE;
        $clubs = $query->paginate($perPage);

        if ($userId && $clubs->isNotEmpty()) {
            $this->attachUserMembershipStatus($clubs->items(), $userId);
        } else {
            foreach ($clubs->items() as $club) {
                $club->is_admin = false;
            }
        }

        return $clubs;
    }

    private function attachUserMembershipStatus(array $clubs, int $userId): void
    {
        $clubIds = array_map(fn ($c) => $c->id, $clubs);
        $memberships = ClubMember::whereIn('club_id', $clubIds)
            ->where('user_id', $userId)
            ->get()
            ->groupBy('club_id');

        foreach ($clubs as $club) {
            $members = $memberships->get($club->id, collect());
            $activeMember = $members->first(fn ($m) =>
                $m->membership_status === ClubMembershipStatus::Joined && $m->status === ClubMemberStatus::Active
            );
            $club->is_member = $activeMember !== null;
            $club->is_admin = $activeMember && $activeMember->role === ClubMemberRole::Admin;
            $club->has_pending_request = $members->contains(fn ($m) =>
                $m->membership_status === ClubMembershipStatus::Pending && $m->invited_by === null
            );
            $club->has_invitation = $members->contains(fn ($m) =>
                $m->membership_status === ClubMembershipStatus::Pending && $m->invited_by !== null
            );
        }
    }

    public function leaveClub(Club $club, int $userId, ?int $transferToUserId = null): array
    {
        $member = $club->activeMembers()->where('user_id', $userId)->first();

        if (!$member) {
            throw new \Exception('Bạn không phải thành viên active của CLB này');
        }

        if ($member->role === ClubMemberRole::Admin) {
            $adminCount = $this->memberService->countActiveAdmins($club);

            if ($adminCount === 1) {
                if (!$transferToUserId) {
                    throw new \Exception('Bạn là admin duy nhất của CLB. Vui lòng nhượng lại quyền quản lý cho thành viên khác trước khi rời.');
                }

                $newAdmin = $club->activeMembers()
                    ->where('user_id', $transferToUserId)
                    ->where('id', '!=', $member->id)
                    ->with('user')
                    ->first();

                if (!$newAdmin) {
                    throw new \Exception('Người được nhượng quyền phải là thành viên active của CLB và không phải chính bạn');
                }

                return DB::transaction(function () use ($member, $newAdmin) {
                    $newAdmin->update([
                        'role' => ClubMemberRole::Admin,
                    ]);

                    $member->update([
                        'role' => ClubMemberRole::Member,
                        'membership_status' => ClubMembershipStatus::Left,
                        'status' => ClubMemberStatus::Inactive,
                        'left_at' => now(),
                    ]);

                    return [
                        'transferred_to' => [
                            'user_id' => $newAdmin->user_id,
                            'user_name' => $newAdmin->user->full_name ?? 'N/A',
                        ],
                    ];
                });
            }
        }

        $member->update([
            'role' => ClubMemberRole::Member,
            'membership_status' => ClubMembershipStatus::Left,
            'status' => ClubMemberStatus::Inactive,
            'left_at' => now(),
        ]);

        return [];
    }

    public function verifyClub(Club $club, bool $isVerified): Club
    {
        $club->update(['is_verified' => $isVerified]);
        return $club->refresh();
    }

    public function updateFund(Club $club, string $qrCodeUrl, int $userId): array
    {
        if (!$club->canManageFinance($userId)) {
            throw new \Exception('Chỉ admin/manager/secretary/treasurer mới có quyền cập nhật quỹ');
        }

        $mainWallet = $club->mainWallet;
        if (!$mainWallet) {
            throw new \Exception('CLB chưa có ví chính');
        }

        $mainWallet->update(['qr_code_url' => $qrCodeUrl]);

        return [
            'club_id' => $club->id,
            'main_wallet_id' => $mainWallet->id,
            'qr_code_url' => $mainWallet->qr_code_url,
        ];
    }

    private function deleteImages(string ...$paths): void
    {
        foreach ($paths as $path) {
            if ($path) {
                $this->imageService->deleteOldImage($path);
            }
        }
    }
}
