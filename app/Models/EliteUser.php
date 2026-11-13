<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class EliteUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'elite_users';

    protected $appends = ['ecole_partenaire'];

   
      protected $fillable = [
        'nom', 'prenom', 'telephone', 'email', 'dernier_diplome', 'ville',
        'partner_id', 'formation_deadline', 'formation_status',
        'password', 'referral_code', 'referred_by', 'solde_points',
        'correspondence_completed', 'profile_chosen', 'parcours_mode', 'photo_url',
        'firebase_uid', 'provider', 'email_verified', 'otp_code', 'otp_expires_at',
        'whatsapp_verified', 'whatsapp_otp_id', 'whatsapp_otp_expires_at', 'whatsapp_otp_purpose',
        'trial_started_at', 'trial_expires_at', 'account_activated', 'activated_at',
    ];

    protected $hidden = ['password', 'remember_token', 'otp_code'];

    protected $casts = [
        'email_verified_at'        => 'datetime',
        'email_verified'           => 'boolean',
        'otp_expires_at'           => 'datetime',
        'whatsapp_verified'        => 'boolean',
        'whatsapp_otp_expires_at'  => 'datetime',
        'correspondence_completed' => 'boolean',
        'profile_chosen'           => 'boolean',
        'account_activated'        => 'boolean',
        'solde_points'             => 'decimal:2',
        'trial_started_at'         => 'datetime',
        'trial_expires_at'         => 'datetime',
        'activated_at'             => 'datetime',
        'formation_deadline'       => 'datetime',
        'formation_status'         => 'string',
    ];

  

    protected function casts(): array
    {
        return [
            'email_verified_at'          => 'datetime',
            'password'                   => 'hashed',
            'correspondence_completed'   => 'boolean',
            'profile_chosen'             => 'boolean',
            'solde_points'               => 'decimal:2',
        ];
    }

    /**
     * Obtenir le profil de carrière actuellement choisi
     */
    public function getSelectedCareerProfile()
    {
        if (!$this->profile_chosen) {
            return null;
        }

        $choice = $this->profileChoice()->with('profile')->first();
        return $choice ? $choice->profile : null;
    }

    /**
     * Génère un code de parrainage unique
     */
    public static function generateReferralCode(): string
    {
        do {
            $code = 'EL' . strtoupper(substr(md5(uniqid()), 0, 6));
        } while (self::where('referral_code', $code)->exists());

        return $code;
    }

    // ============ RELATIONS ============

    public function userPacks()
    {
        return $this->hasMany(UserPack::class, 'user_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function parrain()
    {
        return $this->belongsTo(EliteUser::class, 'referred_by', 'referral_code');
    }

    public function filleuls()
    {
        return $this->hasMany(EliteUser::class, 'referred_by', 'referral_code');
    }

    public function referralHistory()
    {
        return $this->hasMany(ReferralHistory::class, 'parrain_id');
    }

    public function correspondences()
    {
        return $this->hasMany(UserCorrespondence::class, 'user_id');
    }

    public function profileResults()
    {
        return $this->hasMany(UserProfileResult::class, 'user_id');
    }

    public function profileChoice()
    {
        return $this->hasOne(UserProfileChoice::class, 'user_id');
    }

    public function packs()
    {
        return $this->hasMany(UserPack::class, 'user_id');
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class, 'user_id');
    }

    public function quizResults()
    {
        return $this->hasMany(QuizResult::class, 'user_id');
    }

    public function moduleUnlocks()
    {
        return $this->hasMany(ModuleUnlock::class, 'user_id');
    }

    public function chapterUnlocks()
    {
        return $this->hasMany(ChapterUnlock::class, 'user_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    public function sentTransfers()
    {
        return $this->hasMany(Transfer::class, 'sender_id');
    }

    public function receivedTransfers()
    {
        return $this->hasMany(Transfer::class, 'receiver_id');
    }

    // ============ HELPERS ============

    public function getEcolePartenaireAttribute(): ?string
    {
        if ($this->relationLoaded('partner') && $this->partner) {
            return $this->partner->nom;
        }

        if ($this->partner_id) {
            return $this->partner?->nom;
        }

        if ($this->referred_by) {
            $clean = trim(strtoupper($this->referred_by));
            $stripped = preg_replace('/[^A-Z0-9]/', '', $clean);
            $partner = Partner::where('active', true)
                ->where(function ($query) use ($clean, $stripped) {
                    $query->whereRaw('UPPER(TRIM(code_partenaire)) = ?', [$clean])
                          ->orWhereRaw('UPPER(TRIM(nom)) = ?', [$clean]);
                    if (!empty($stripped)) {
                        $query->orWhereRaw("REPLACE(REPLACE(UPPER(code_partenaire), '-', ''), ' ', '') = ?", [$stripped]);
                    }
                })->first();

            if ($partner) {
                return $partner->nom;
            }
        }

        return null;
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->prenom} {$this->nom}";
    }

    public function hasCompletedCorrespondence(): bool
    {
        return $this->correspondence_completed;
    }

    public function hasChosenProfile(): bool
    {
        return $this->profile_chosen;
    }

    public function getChosenProfile()
    {
        return $this->profileChoice?->profile;
    }

    public function hasPack(int $packId): bool
    {
        return $this->packs()->where('pack_id', $packId)->exists();
    }

    public function canAfford(int $points): bool
    {
        return $this->solde_points >= $points;
    }

    public function addPoints(float $points): void
    {
        $this->increment('solde_points', $points);
    }

    public function deductPoints(float $points): bool
    {
        if (!$this->canAfford($points)) {
            return false;
        }
        $this->decrement('solde_points', $points);
        return true;
    }

     public function isProfileComplete(): bool
    {
        return !empty($this->nom)
            && !empty($this->prenom)
            && !empty($this->dernier_diplome)
            && !empty($this->ville);
    }
    
    // ─── OTP helpers ──────────────────────────────────────────────
 
    public function generateEmailOtp(): string
    {
        $otp = (string) random_int(100000, 999999);
        $this->update([
            'email_otp'            => $otp,
            'email_otp_expires_at' => now()->addMinutes(15),
        ]);
        return $otp;
    }
 
    public function verifyEmailOtp(string $otp): bool
    {
        return $this->email_otp === $otp
            && $this->email_otp_expires_at
            && now()->lessThanOrEqualTo($this->email_otp_expires_at);
    }
 
    public function markEmailVerified(): void
    {
        $this->update([
            'email_verified'         => true,
            'email_verified_at'      => now(),
            'email_otp'              => null,
            'email_otp_expires_at'   => null,
        ]);
    }
    
}