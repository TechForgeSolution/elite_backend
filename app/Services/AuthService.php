<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\EliteUser;
use App\Models\Partner;
use App\Models\ReferralHistory;
use App\Models\SystemSetting;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private FirebaseService $firebase) {}

    // ──────────────────────────────────────────
    // INSCRIPTION
    // ──────────────────────────────────────────

    public function register(array $data): array
    {
        $resolved = $this->resolveReferral($data['referral_code'] ?? '');
        $otp = $this->requestWhatsappOtp($data['telephone']);

        $user = DB::transaction(function () use ($data, $resolved, $otp) {
            return EliteUser::create([
                'nom'              => $data['nom'],
                'prenom'           => $data['prenom'],
                'telephone'        => $data['telephone'] ?? null,
                'dernier_diplome'  => $data['dernier_diplome'],
                'ville'            => $data['ville'],
                'password'         => Hash::make($data['password']),
                'referral_code'    => EliteUser::generateReferralCode(),
                'partner_id'       => $resolved['partner']?->id,
                'referred_by'      => $resolved['code'],
                'provider'         => 'phone',
                'email_verified'   => false,
                'whatsapp_verified' => false,
                'whatsapp_otp_id' => $otp['id'],
                'whatsapp_otp_expires_at' => now()->addMinutes(5),
                'whatsapp_otp_purpose' => 'registration',
            ]);
        });

        return [
            'needs_otp' => true,
            'telephone' => $user->telephone,
            'message' => 'Un code de vérification a été envoyé sur WhatsApp.',
        ];
    }

    // ──────────────────────────────────────────
    // CONNEXION CLASSIQUE
    // ──────────────────────────────────────────

    public function login(?string $telephone, ?string $email, string $password): array
    {
        if ($telephone) {
            $user = EliteUser::where('telephone', $telephone)->first();
        } elseif ($email) {
            $user = EliteUser::where('email', $email)->first();
        } else {
            throw ValidationException::withMessages([
                'identifier' => ['Téléphone ou email requis.'],
            ]);
        }

        if (!$user || !$user->password || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => ['Les identifiants sont incorrects.'],
            ]);
        }

        if ($user->provider === 'phone' && !$user->whatsapp_verified) {
            if (!$user->whatsapp_otp_id || !$user->whatsapp_otp_expires_at || now()->greaterThanOrEqualTo($user->whatsapp_otp_expires_at)) {
                $this->sendWhatsappOtp($user, 'registration');
            }

            return [
                'needs_otp' => true,
                'telephone' => $user->telephone,
                'message' => 'Vérifiez votre numéro WhatsApp pour continuer.',
            ];
        }

        // Email non vérifié (anciens comptes)
        if ($user->provider === 'email' && !$user->email_verified) {
            $this->sendOtp($user);
            return [
                'needs_otp' => true,
                'email'     => $user->email,
                'message'   => 'Votre email n\'est pas encore vérifié. Un nouveau code vous a été envoyé.',
            ];
        }

        $token = $user->createToken('elite-mobile')->plainTextToken;

        return [
            'needs_otp'               => false,
            'user'                    => $user,
            'token'                   => $token,
            'requires_correspondence' => !$user->correspondence_completed,
            'requires_profile_choice' => $user->correspondence_completed && !$user->profile_chosen,
        ];
    }

    // ──────────────────────────────────────────
    // CONNEXION APPLE POUR LES COMPTES DÉJÀ CRÉÉS
    // ──────────────────────────────────────────

    public function socialLogin(string $firebaseToken): array
    {
        $firebaseUser = $this->firebase->verifyToken($firebaseToken);

        if (!$firebaseUser) {
            throw ValidationException::withMessages([
                'firebase_token' => ['Token Firebase invalide ou expiré.'],
            ]);
        }

        $providerIds = array_column($firebaseUser['providerUserInfo'] ?? [], 'providerId');
        if (!in_array('apple.com', $providerIds, true)) {
            throw ValidationException::withMessages([
                'provider' => ['La connexion Apple est réservée aux comptes déjà créés.'],
            ]);
        }

        $firebaseUid = $firebaseUser['localId'];
        $email       = $firebaseUser['email'] ?? null;
        $photoUrl    = $firebaseUser['photoUrl'] ?? null;

        // Chercher par firebase_uid d'abord, puis par email
        $user = EliteUser::where('firebase_uid', $firebaseUid)->first()
             ?? ($email ? EliteUser::where('email', $email)->first() : null);

        if (!$user) {
            throw ValidationException::withMessages([
                'provider' => ['Créez d’abord votre compte avec un numéro WhatsApp.'],
            ]);
        }

        $user->update([
            'firebase_uid' => $firebaseUid,
            'email_verified' => true,
            'photo_url' => $user->photo_url ?? $photoUrl,
        ]);

        $token           = $user->createToken('elite-mobile')->plainTextToken;
        $needsCompletion = !$user->isProfileComplete() || empty($user->dernier_diplome) || empty($user->ville);

        return [
            'user'                    => $user,
            'token'                   => $token,
            'needs_completion'        => $needsCompletion,
            'requires_correspondence' => !$user->correspondence_completed,
            'requires_profile_choice' => $user->correspondence_completed && !$user->profile_chosen,
        ];
    }

    // ──────────────────────────────────────────
    // COMPLÉTION PROFIL SOCIAL
    // ──────────────────────────────────────────

    public function completeSocialProfile(EliteUser $user, array $data): array
    {
        $referralCode = $data['referral_code'] ?? '';
        $resolved = $this->resolveReferral($referralCode);

        $updateData = [
            'nom'             => $data['nom'],
            'prenom'          => $data['prenom'],
            'dernier_diplome' => $data['dernier_diplome'],
            'ville'           => $data['ville'],
            'referred_by'     => $resolved['code'],
        ];

        if ($resolved['partner']) {
            $updateData['partner_id'] = $resolved['partner']->id;
        }

        $updateData['telephone'] = $data['telephone'];
        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        if ($user->whatsapp_verified && $resolved['type'] === 'user' && $resolved['parrain']) {
            $this->creditReferralPoints($resolved['parrain'], $user);
        }

        $freshUser = $user->fresh();
        $freshUser->load('partner');

        if (!$freshUser->whatsapp_verified) {
            $this->sendWhatsappOtp($freshUser, 'registration');
            return [
                'needs_otp' => true,
                'telephone' => $freshUser->telephone,
            ];
        }

        return [
            'user'                    => $freshUser,
            'requires_correspondence' => !$user->correspondence_completed,
            'requires_profile_choice' => $user->correspondence_completed && !$user->profile_chosen,
        ];
    }

    // ──────────────────────────────────────────
    // OTP EMAIL
    // ──────────────────────────────────────────

    public function sendOtp(EliteUser $user): void
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'otp_code'       => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($user->email)->send(new OtpMail($otp, $user->prenom));
    }

    public function verifyEmailOtp(string $email, string $code): array
    {
        $user = EliteUser::where('email', $email)->first();

        if (!$user) {
            throw ValidationException::withMessages(['email' => ['Email introuvable.']]);
        }

        if ($user->email_verified) {
            // Déjà vérifié — juste connecter
            $token = $user->createToken('elite-mobile')->plainTextToken;
            return $this->buildLoginResponse($user, $token);
        }

        if (!$user->otp_code || $user->otp_code !== $code) {
            throw ValidationException::withMessages(['code' => ['Code incorrect.']]);
        }

        if ($user->otp_expires_at && now()->isAfter($user->otp_expires_at)) {
            throw ValidationException::withMessages(['code' => ['Code expiré. Demandez un nouveau code.']]);
        }

        $user->update([
            'email_verified' => true,
            'otp_code'       => null,
            'otp_expires_at' => null,
        ]);

        $token = $user->createToken('elite-mobile')->plainTextToken;
        return $this->buildLoginResponse($user, $token);
    }

    public function resendEmailOtp(string $email): void
    {
        $user = EliteUser::where('email', $email)->first();
        if (!$user || $user->email_verified) return;
        $this->sendOtp($user);
    }

    public function resendWhatsappOtp(string $telephone): void
    {
        $user = EliteUser::where('telephone', $telephone)->first();
        if (!$user || $user->whatsapp_verified) {
            throw ValidationException::withMessages([
                'telephone' => ['Aucun compte en attente de vérification ne correspond à ce numéro.'],
            ]);
        }

        $this->sendWhatsappOtp($user, 'registration');
    }

    public function verifyWhatsappOtp(string $telephone, string $code, bool $createToken = true): array
    {
        $user = EliteUser::where('telephone', $telephone)->first();
        if (!$user || !$user->whatsapp_otp_id || !$user->whatsapp_otp_expires_at ||
            now()->greaterThanOrEqualTo($user->whatsapp_otp_expires_at) ||
            $user->whatsapp_otp_purpose !== 'registration') {
            throw ValidationException::withMessages([
                'code' => ['Ce code est expiré ou ne correspond pas à une inscription en attente.'],
            ]);
        }

        $this->verifyCareyOtp($user->whatsapp_otp_id, $code);

        return DB::transaction(function () use ($user, $createToken) {
            $user = EliteUser::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->whatsapp_verified || $user->whatsapp_otp_purpose !== 'registration') {
                throw ValidationException::withMessages(['code' => ['Ce code a déjà été utilisé.']]);
            }

            $user->update([
                'whatsapp_verified' => true,
                'whatsapp_otp_id' => null,
                'whatsapp_otp_expires_at' => null,
                'whatsapp_otp_purpose' => null,
            ]);

            $resolved = $this->findReferralEntity($user->referred_by ?? '');
            if (($resolved['type'] ?? null) === 'user' && $resolved['parrain']) {
                $this->creditReferralPoints($resolved['parrain'], $user);
            }

            $user->load('partner');
            if (!$createToken) {
                return ['user' => $user];
            }

            $token = $user->createToken('elite-mobile')->plainTextToken;
            return $this->buildLoginResponse($user, $token);
        });
    }

    public function sendPasswordResetWhatsappOtp(string $telephone): void
    {
        $user = EliteUser::where('telephone', $telephone)->first();
        if ($user) {
            $this->sendWhatsappOtp($user, 'password_reset');
        }
    }

    public function resetPasswordWithWhatsappOtp(string $telephone, string $code, string $password): void
    {
        $user = EliteUser::where('telephone', $telephone)->first();
        if (!$user || !$user->whatsapp_otp_id || !$user->whatsapp_otp_expires_at ||
            now()->greaterThanOrEqualTo($user->whatsapp_otp_expires_at) ||
            $user->whatsapp_otp_purpose !== 'password_reset') {
            throw ValidationException::withMessages([
                'code' => ['Ce code est expiré ou ne correspond pas à une demande de réinitialisation.'],
            ]);
        }

        $this->verifyCareyOtp($user->whatsapp_otp_id, $code);

        DB::transaction(function () use ($user, $password) {
            $user = EliteUser::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->whatsapp_otp_purpose !== 'password_reset') {
                throw ValidationException::withMessages(['code' => ['Ce code a déjà été utilisé.']]);
            }

            $user->update([
                'password' => Hash::make($password),
                'whatsapp_otp_id' => null,
                'whatsapp_otp_expires_at' => null,
                'whatsapp_otp_purpose' => null,
            ]);
            $user->tokens()->delete();
        });
    }

    private function sendWhatsappOtp(EliteUser $user, string $purpose): void
    {
        $response = $this->requestWhatsappOtp($user->telephone);

        $user->update([
            'whatsapp_otp_id' => $response['id'],
            'whatsapp_otp_expires_at' => now()->addMinutes(5),
            'whatsapp_otp_purpose' => $purpose,
        ]);
    }

    private function requestWhatsappOtp(string $telephone): array
    {
        $response = $this->careyRequest('/otp/send', [
            'to' => $this->formatWhatsappTelephone($telephone),
            'length' => 6,
            'ttl_minutes' => 5,
            'lang' => 'fr',
            'app_name' => config('services.carey.app_name'),
        ], 'telephone');

        if (empty($response['id'])) {
            Log::error('Carey OTP response did not contain an OTP identifier.');
            abort(503, 'Le service de vérification WhatsApp est temporairement indisponible.');
        }

        return $response;
    }

    private function formatWhatsappTelephone(string $telephone): string
    {
        $digits = preg_replace('/\D+/', '', $telephone) ?? '';

        return strlen($digits) === 9 ? '237' . $digits : $digits;
    }

    private function verifyCareyOtp(string $id, string $code): void
    {
        $response = $this->careyRequest('/otp/verify', [
            'id' => $id,
            'code' => $code,
        ], 'code');

        if (($response['verified'] ?? false) !== true) {
            throw ValidationException::withMessages(['code' => ['Le code WhatsApp est incorrect.']]);
        }
    }

    private function careyRequest(string $path, array $payload, string $field): array
    {
        $apiKey = config('services.carey.api_key');
        if (!$apiKey) {
            Log::error('Carey API key is not configured.');
            abort(503, 'La vérification WhatsApp n’est pas configurée.');
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(15)
                ->post(rtrim(config('services.carey.api_url'), '/') . $path, $payload);
        } catch (ConnectionException $exception) {
            Log::warning('Carey API connection failed.', ['path' => $path]);
            abort(503, 'Le service WhatsApp est temporairement indisponible.');
        }

        if (!$response->successful()) {
            $error = $response->json('error');
            if ($response->status() === 429 || $error === 'rate_limited') {
                throw ValidationException::withMessages([$field => ['Trop de tentatives. Veuillez patienter avant de réessayer.']]);
            }
            if ($error === 'not_on_whatsapp') {
                throw ValidationException::withMessages([$field => ['Ce numéro ne possède pas de compte WhatsApp.']]);
            }
            if (in_array($error, ['invalid_code', 'expired', 'blocked', 'already_verified'], true)) {
                throw ValidationException::withMessages(['code' => ['Le code est invalide, expiré ou déjà utilisé.']]);
            }

            Log::warning('Carey API request failed.', ['path' => $path, 'status' => $response->status(), 'error' => $error]);
            abort(503, 'Le service WhatsApp est temporairement indisponible.');
        }

        $decoded = $response->json();
        return is_array($decoded) ? $decoded : [];
    }

    // ──────────────────────────────────────────
    // DÉCONNEXION / PROFIL
    // ──────────────────────────────────────────

    public function logout(EliteUser $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function findReferralEntity(string $code): ?array
    {
        $clean = trim(strtoupper($code));
        if (empty($clean)) {
            return null;
        }

        $stripped = preg_replace('/[^A-Z0-9]/', '', $clean);

        // 1. Partenaire (par code_partenaire ou nom)
        $partner = Partner::where('active', true)
            ->where(function ($query) use ($clean, $stripped) {
                $query->whereRaw('UPPER(TRIM(code_partenaire)) = ?', [$clean])
                      ->orWhereRaw('UPPER(TRIM(nom)) = ?', [$clean])
                      ->orWhere('nom', 'LIKE', "%{$clean}%");
                if (!empty($stripped)) {
                    $query->orWhereRaw("REPLACE(REPLACE(UPPER(code_partenaire), '-', ''), ' ', '') = ?", [$stripped]);
                }
            })->first();

        if ($partner) {
            return [
                'type'       => 'partner',
                'partner'    => $partner,
                'partner_id' => $partner->id,
                'nom'        => $partner->nom,
                'parrain'    => null,
                'code'       => $partner->code_partenaire ?: $clean,
                'label'      => "Centre Partenaire : {$partner->nom}",
            ];
        }

        // 2. Parrain utilisateur
        $parrain = EliteUser::where(function ($query) use ($clean, $stripped) {
            $query->whereRaw('UPPER(TRIM(referral_code)) = ?', [$clean]);
            if (!empty($stripped)) {
                $query->orWhereRaw("REPLACE(REPLACE(UPPER(referral_code), '-', ''), ' ', '') = ?", [$stripped]);
            }
        })->first();

        if ($parrain) {
            return [
                'type'       => 'user',
                'partner'    => null,
                'parrain'    => $parrain,
                'code'       => $parrain->referral_code,
                'label'      => "Parrain : {$parrain->prenom} {$parrain->nom}" . ($parrain->ville ? " ({$parrain->ville})" : ""),
            ];
        }

        // 3. Code système officiel (ELITE2026, ELITE2024, etc.)
        $defaultCode = strtoupper(trim(SystemSetting::getDefaultReferralCode()));
        $validSystemCodes = array_filter(array_unique([$defaultCode, 'ELITE2026', 'ELITE2024', 'ELITE']));
        if (in_array($clean, $validSystemCodes, true) || (!empty($stripped) && in_array($stripped, $validSystemCodes, true))) {
            return [
                'type'    => 'default',
                'partner' => null,
                'parrain' => null,
                'code'    => $defaultCode ?: 'ELITE2026',
                'label'   => 'Code Officiel Elite',
            ];
        }

        return null;
    }

    public function checkReferralCode(string $code): array
    {
        $entity = $this->findReferralEntity($code);
        if ($entity) {
            return array_merge(['valid' => true], $entity);
        }

        return [
            'valid'   => false,
            'message' => 'Code de parrainage ou de centre partenaire invalide.',
        ];
    }

    public function updateProfile(EliteUser $user, array $data): EliteUser
    {
        $allowed = ['nom', 'prenom', 'email', 'ville', 'photo_url', 'password'];
        $update  = array_intersect_key($data, array_flip($allowed));

        if (isset($update['password'])) {
            $update['password'] = Hash::make($update['password']);
        }

        $user->update($update);
        return $user->fresh();
    }

    // ──────────────────────────────────────────
    // HELPERS PRIVÉS
    // ──────────────────────────────────────────

    public function resolveReferral(string $referralCode): array
    {
        $code = trim($referralCode);
        if (empty($code)) {
            throw ValidationException::withMessages([
                'referral_code' => ['Le code de parrainage ou de centre partenaire est obligatoire.'],
            ]);
        }

        $entity = $this->findReferralEntity($code);
        if ($entity) {
            return $entity;
        }

        throw ValidationException::withMessages([
            'referral_code' => ['Le code de parrainage ou de centre partenaire est invalide.'],
        ]);
    }

    private function creditReferralPoints(EliteUser $parrain, EliteUser $filleul): void
    {
        $points = SystemSetting::getPointsPerReferral();
        $parrain->addPoints($points);

        ReferralHistory::create([
            'parrain_id'   => $parrain->id,
            'filleul_id'   => $filleul->id,
            'points_gagnes'=> $points,
        ]);

        Transaction::create([
            'user_id'     => $parrain->id,
            'type'        => 'parrainage',
            'points'      => $points,
            'reference'   => Transaction::generateReference(),
            'description' => "Bonus parrainage - {$filleul->full_name}",
            'metadata'    => ['filleul_id' => $filleul->id, 'filleul_nom' => $filleul->full_name],
            'statut'      => 'complete',
        ]);
    }

    private function buildLoginResponse(EliteUser $user, string $token): array
    {
        $user->load('partner');
        return [
            'user'                    => $user,
            'token'                   => $token,
            'requires_correspondence' => !$user->correspondence_completed,
            'requires_profile_choice' => $user->correspondence_completed && !$user->profile_chosen,
        ];
    }

}