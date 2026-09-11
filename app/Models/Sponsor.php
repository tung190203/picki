<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sponsor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo_url',
        'logo_dark_url',
        'website_url',
        'display_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope query lấy danh sách sponsor đang active theo thứ tự hiển thị
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->orderBy('display_order', 'asc')
            ->orderBy('id', 'desc');
    }
}
