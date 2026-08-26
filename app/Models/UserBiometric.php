<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBiometric extends Model
{
    use HasFactory;

    protected $table = 'user_biometrics';

    protected $fillable = [
        'user_id',
        'credential_id',
        'public_key',
        'device_name',
        'platform',
        'counter',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'counter' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
