<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    private ?string $webApiKey;

    public function __construct()
    {
        $this->webApiKey = config('services.firebase.web_api_key');
    }

    /**
     * Vérifie un Firebase ID token et retourne les infos utilisateur.
     * Retourne null si le token est invalide.
     */
    public function verifyToken(string $idToken): ?array
    {
        if (!$this->webApiKey) {
            Log::error('Firebase web API key is not configured.');
            return null;
        }

        try {
            $response = Http::timeout(10)->post(
                "https://identitytoolkit.googleapis.com/v1/accounts:lookup?key={$this->webApiKey}",
                ['idToken' => $idToken]
            );

            if ($response->failed()) {
                Log::warning('Firebase token verification failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $users = $response->json('users', []);
            if (empty($users)) {
                return null;
            }

            return $users[0]; // { localId, email, displayName, photoUrl, providerUserInfo, ... }

        } catch (\Throwable $e) {
            Log::error('FirebaseService::verifyToken exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

}