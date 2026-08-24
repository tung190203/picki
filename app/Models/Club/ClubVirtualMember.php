<?php

namespace App\Models\Club;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClubVirtualMember extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'club_virtual_members';

    protected $fillable = [
        'club_id',
        'name',
        'avatar_url',
        'created_by',
        'notes',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function getAvatarUrlAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        return str_starts_with($value, 'http') ? $value : asset('storage/' . $value);
    }
}
