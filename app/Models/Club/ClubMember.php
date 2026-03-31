<?php

namespace App\Models\Club;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClubMember extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'club_members';
    protected $perPage = 10;

    protected $fillable = [
        'club_id',
        'user_id',
        'invited_by',
        'role',
        'position',
        'membership_status',
        'status',
        'message',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'joined_at',
        'left_at',
        'notes',
        'is_manager',
    ];

    protected $casts = [
        'role' => ClubMemberRole::class,
        'membership_status' => ClubMembershipStatus::class,
        'status' => ClubMemberStatus::class,
        'reviewed_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'is_manager' => 'boolean',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Admin mời vào CLB (user phải đồng ý). null = user tự gửi request. */
    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** Alias của inviter — người đã mời user vào CLB. */
    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** Là lời mời từ admin (chờ user đồng ý). */
    public function isInvitation(): bool
    {
        return $this->invited_by !== null;
    }

    /** Đang tham gia CLB (membership_status = joined, status = active). */
    public function scopeActive($query)
    {
        return $query->where('membership_status', ClubMembershipStatus::Joined)
            ->where('status', ClubMemberStatus::Active);
    }

    /** Chờ duyệt (yêu cầu vào hoặc lời mời) — status = pending. */
    public function scopePending($query)
    {
        return $query->where('membership_status', ClubMembershipStatus::Pending)
            ->where('status', ClubMemberStatus::Pending);
    }

    /** Đã join (membership_status = joined), bất kể status active/inactive/suspended. */
    public function scopeJoined($query)
    {
        return $query->where('membership_status', ClubMembershipStatus::Joined);
    }

    /** Đã rời hoặc bị đuổi — có thể gửi request lại. */
    public function scopeLeftOrSuspended($query)
    {
        return $query->where('membership_status', ClubMembershipStatus::Left);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function isAdmin()
    {
        return $this->role === ClubMemberRole::Admin;
    }

    public function isManager()
    {
        return in_array($this->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary]);
    }

    public function canManageFinance()
    {
        return in_array($this->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary, ClubMemberRole::Treasurer]);
    }

    public function canManageMembers()
    {
        return in_array($this->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary]);
    }

    /** Đang chờ duyệt (yêu cầu vào hoặc lời mời) — status = pending. */
    public function isJoinRequest(): bool
    {
        return $this->membership_status === ClubMembershipStatus::Pending
            && $this->status === ClubMemberStatus::Pending;
    }

    /** Đang tham gia CLB (joined + active). */
    public function isJoined(): bool
    {
        return $this->membership_status === ClubMembershipStatus::Joined
            && $this->status === ClubMemberStatus::Active;
    }

    /** Đã rejected, left hoặc cancelled — có thể gửi request lại. */
    public function canSendJoinRequest(): bool
    {
        return in_array($this->membership_status, [
            ClubMembershipStatus::Rejected,
            ClubMembershipStatus::Left,
            ClubMembershipStatus::Cancelled,
        ], true);
    }
}
