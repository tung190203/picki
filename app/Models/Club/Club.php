<?php

namespace App\Models\Club;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Enums\ClubStatus;
use App\Models\User;
use App\Models\Tournament;
use App\Models\MiniTournament;
use Database\Factories\ClubFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Club extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return ClubFactory::new();
    }

    const PER_PAGE = 10;

    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'logo_url',
        'status',
        'is_public',
        'is_verified',
        'created_by',
    ];

    protected $casts = [
        'status' => ClubStatus::class,
        'is_public' => 'boolean',
        'is_verified' => 'boolean',
    ];

    /**
     * Trả về URL đầy đủ của logo (lưu trong DB là path hoặc URL cũ).
     */
    public function getLogoUrlAttribute(): ?string
    {
        $value = $this->attributes['logo_url'] ?? null;
        if (empty($value)) {
            return null;
        }
        return str_starts_with($value, 'http') ? $value : asset('storage/' . $value);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->hasMany(ClubMember::class)
            ->whereHas('user') // Chỉ lấy members có user tồn tại
            ->where('membership_status', ClubMembershipStatus::Joined); // Chỉ lấy members đã joined (loại bỏ pending, left, rejected, cancelled)
    }

    /** Thành viên đang tham gia (membership_status = joined, status = active). */
    public function activeMembers()
    {
        return $this->hasMany(ClubMember::class)
            ->whereHas('user') // Chỉ lấy members có user tồn tại
            ->where('membership_status', ClubMembershipStatus::Joined)
            ->where('status', ClubMemberStatus::Active);
    }

    /** Yêu cầu/lời mời chờ duyệt (membership_status = pending, status = pending). */
    public function pendingJoinRequests()
    {
        return $this->hasMany(ClubMember::class)
            ->whereHas('user') // Chỉ lấy members có user tồn tại
            ->where('membership_status', ClubMembershipStatus::Pending);
    }

    /** Thành viên đã join (membership_status = joined). */
    public function joinedMembers()
    {
        return $this->hasMany(ClubMember::class)
            ->whereHas('user') // Chỉ lấy members có user tồn tại
            ->where('membership_status', ClubMembershipStatus::Joined);
    }

    public function profile()
    {
        return $this->hasOne(ClubProfile::class);
    }

    public function wallet()
    {
        return $this->hasOne(ClubWallet::class);
    }

    // Alias giữ tương thích với code cũ
    public function mainWallet()
    {
        return $this->hasOne(ClubWallet::class);
    }

    /**
     * Trả về wallet đầu tiên có qr_code_url không null (mã QR chung của CLB).
     */
    public function activeQrWallet(): ?ClubWallet
    {
        return $this->hasOne(ClubWallet::class)->whereNotNull('qr_code_url')->first();
    }

    public function monthlyFees()
    {
        return $this->hasMany(ClubMonthlyFee::class);
    }

    public function activeMonthlyFees()
    {
        return $this->hasMany(ClubMonthlyFee::class)->where('is_active', true);
    }

    public function fundCollections()
    {
        return $this->hasMany(ClubFundCollection::class);
    }

    public function expenses()
    {
        return $this->hasMany(ClubExpense::class);
    }

    public function activities()
    {
        return $this->hasMany(ClubActivity::class);
    }

    public function notifications()
    {
        return $this->hasMany(ClubNotification::class);
    }

    public function reports()
    {
        return $this->hasMany(ClubReport::class);
    }

    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }

    public function miniTournaments()
    {
        return $this->hasMany(MiniTournament::class);
    }

    public function scopeWithFullRelations($query)
    {
        return $query->with([
            'creator',
            'profile',
            'members.user.vnduprScores',
            'members.reviewer',
            'mainWallet',
            'activeMembers.user.vnduprScores'
        ]);
    }

    public function scopeWithListRelations($query)
    {
        return $query->with(['profile:id,club_id,cover_image_url,description'])
            ->withCount(['activeMembers as members_count']);
    }

    public function scopeSearch($query, $fillable, $searchTerm)
    {
        if ($searchTerm) {
            $query->where(function ($q) use ($fillable, $searchTerm) {
                foreach ($fillable as $field) {
                    $q->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                }
            });
        }
        return $query;
    }

    public function hasMember($userId)
    {
        return $this->members()
            ->where('user_id', $userId)
            ->where('status', ClubMemberStatus::Active)
            ->exists();
    }

    public function isMember($userId)
    {
        return $this->activeMembers()->where('user_id', $userId)->exists();
    }

    public function canSendJoinRequest($userId): bool
    {
        $existing = ClubMember::where('club_id', $this->id)
            ->where('user_id', $userId)
            ->first();
        if (!$existing) {
            return true;
        }
        return in_array($existing->membership_status, [
            ClubMembershipStatus::Rejected,
            ClubMembershipStatus::Left,
            ClubMembershipStatus::Cancelled,
        ], true);
    }

    public function hasPendingRequest($userId): bool
    {
        return ClubMember::where('club_id', $this->id)
            ->where('user_id', $userId)
            ->where('membership_status', ClubMembershipStatus::Pending)
            ->exists();
    }

    public function canManage($userId)
    {
        $member = $this->activeMembers()->where('user_id', $userId)->first();
        if (!$member) return false;

        return in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary]);
    }

    /** Chỉ admin và thư ký mới có quyền sửa footer. */
    public function canEditFooter($userId): bool
    {
        $member = $this->activeMembers()->where('user_id', $userId)->first();
        if (!$member) return false;

        return in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Secretary]);
    }

    public function canManageFinance($userId)
    {
        $member = $this->activeMembers()->where('user_id', $userId)->first();
        if (!$member) return false;

        return in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary, ClubMemberRole::Treasurer]);
    }

    public function countActiveAdmins(): int
    {
        return $this->activeMembers()
            ->where('role', ClubMemberRole::Admin)
            ->count();
    }

    public function hasAtLeastOneAdminAfterRemoving($memberIdToRemove): bool
    {
        $remainingAdmins = $this->activeMembers()
            ->where('role', ClubMemberRole::Admin)
            ->where('id', '!=', $memberIdToRemove)
            ->count();

        return $remainingAdmins > 0;
    }

    public function scopeInBounds($query, $minLat, $maxLat, $minLng, $maxLng)
    {
        return $query->whereBetween('latitude', [$minLat, $maxLat])
            ->whereBetween('longitude', [$minLng, $maxLng]);
    }

    public function scopeNearBy($query, float $lat, float $lng, float $radiusKm = 5)
    {
        $haversine = "(6371 * acos(cos(radians($lat))
                * cos(radians(latitude))
                * cos(radians(longitude) - radians($lng))
                + sin(radians($lat))
                * sin(radians(latitude))))";

        return $query->select('*')
            ->selectRaw("$haversine AS distance")
            ->having('distance', '<=', $radiusKm)
            ->orderBy('distance');
    }

    public function scopeOrderByDistance($query, $lat, $lng)
    {
        return $query
            ->select('*')
            ->selectRaw("
                (
                    6371 * acos(
                        cos(radians(?))
                        * cos(radians(latitude))
                        * cos(radians(longitude) - radians(?))
                        + sin(radians(?))
                        * sin(radians(latitude))
                    )
                ) AS distance
            ", [$lat, $lng, $lat])
            ->orderByRaw('latitude IS NULL OR longitude IS NULL')
            ->orderBy('distance', 'asc');
    }
}
