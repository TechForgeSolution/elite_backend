<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DashboardAdvertisement extends Model
{
    protected $fillable = ['title', 'banner_path', 'redirect_url', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function getBannerUrlAttribute(): string
    {
        $path = Storage::disk('public')->url($this->banner_path);
        return str_starts_with($path, 'http') ? $path : url($path);
    }
}
