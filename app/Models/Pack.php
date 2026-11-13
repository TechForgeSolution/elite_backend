<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\SystemSetting;

class Pack extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'nom',
        'slug',
        'description',
        'image_url',
        'banner_path',
        'niveau_requis',
        'durees_disponibles',
        'diplomes_possibles',
        'prix_points',
        'prix_fcfa',
        'debouches',
        'ordre',
        'active',
    ];

    protected $casts = [
        'durees_disponibles' => 'array',
        'diplomes_possibles' => 'array',
        'debouches' => 'array',
        'prix_fcfa' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function modules()
    {
        return $this->hasMany(Module::class, 'pack_id')->orderBy('ordre');
    }

    public function profiles()
    {
        return $this->belongsToMany(CareerProfile::class, 'pack_profiles', 'pack_id', 'profile_id')
            ->withPivot('priorite')
            ->withTimestamps();
    }

    public function userPacks()
    {
        return $this->hasMany(UserPack::class, 'pack_id');
    }

    public function roadmapSteps()
    {
        return $this->hasMany(RoadmapStep::class, 'pack_recommande_id');
    }

    public function getTotalLessonsAttribute(): int
    {
        return $this->modules()
            ->with('lessons')
            ->get()
            ->flatMap(fn($m) => $m->lessons)
            ->count();
    }

    public function getTotalDurationAttribute(): int
    {
        return $this->modules()
            ->with('lessons')
            ->get()
            ->flatMap(fn($m) => $m->lessons)
            ->sum('duree_minutes');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForProfile($query, int $profileId)
    {
        return $query->whereHas('profiles', function ($q) use ($profileId) {
            $q->where('career_profiles.id', $profileId);
        })->orderByRaw('(SELECT priorite FROM pack_profiles WHERE pack_profiles.pack_id = packs.id AND pack_profiles.profile_id = ?) DESC', [$profileId]);
    }

    public function getDureesDisponiblesAttribute($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (empty($value)) {
            return [];
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            if (is_string($decoded)) {
                return array_values(array_filter(array_map('trim', explode(',', $decoded))));
            }
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }
        return [];
    }

    public function getDiplomesPossiblesAttribute($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (empty($value)) {
            return [];
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            if (is_string($decoded)) {
                return array_values(array_filter(array_map('trim', explode(',', $decoded))));
            }
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }
        return [];
    }

    public function getDebouchesAttribute($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (empty($value)) {
            return [];
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            if (is_string($decoded)) {
                return array_values(array_filter(array_map('trim', explode(',', $decoded))));
            }
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }
        return [];
    }

    public function getPrixFcfaEffectifAttribute(): float
    {
        return (float) ($this->prix_fcfa ?? ((float) $this->prix_points * SystemSetting::getTauxConversionFcfaPoints()));
    }
}