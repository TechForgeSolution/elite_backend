<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    // ── Inscription ───────────────────────────────────────────
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'              => 'required|string|max:255',
            'prenom'           => 'required|string|max:255',
            'telephone'        => 'required|string|max:20|unique:elite_users,telephone',
            'dernier_diplome'  => 'required|in:primaire,secondaire,universitaire',
            'ville'            => 'required|string|max:255',
            'password'         => 'required|string|min:6|confirmed',
            'referral_code'    => 'required|string|max:50',
        ]);

        $result = $this->authService->register($data);

        if ($result['needs_otp'] ?? false) {
            return response()->json(['success' => true, 'data' => $result]);
        }

        return response()->json(['success' => true, 'data' => $result], 201);
    }

    // ── Connexion classique ───────────────────────────────────
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telephone' => 'required_without:email|nullable|string',
            'email'     => 'required_without:telephone|nullable|email',
            'password'  => 'required|string',
        ]);

        $result = $this->authService->login(
            $data['telephone'] ?? null,
            $data['email']     ?? null,
            $data['password']
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    // ── Connexion Apple pour comptes déjà créés ───────────────
    public function socialLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'firebase_token' => 'required|string',
            'provider'       => 'required|in:apple',
        ]);

        $result = $this->authService->socialLogin($data['firebase_token']);
        return response()->json(['success' => true, 'data' => $result]);
    }

    // ── Complétion profil social ──────────────────────────────
    public function completeSocialProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'             => 'required|string|max:255',
            'prenom'          => 'required|string|max:255',
            'dernier_diplome' => 'required|in:primaire,secondaire,universitaire',
            'ville'           => 'required|string|max:255',
            'referral_code'   => 'required|string|max:50',
            'telephone'       => 'required|string|max:20|unique:elite_users,telephone,' . $request->user()->id,
            'password'        => 'nullable|string|min:6|confirmed',
        ]);

        $result = $this->authService->completeSocialProfile($request->user(), $data);
        return response()->json(['success' => true, 'data' => $result]);
    }

    // ── OTP Email ─────────────────────────────────────────────
    public function verifyEmailOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        $result = $this->authService->verifyEmailOtp($data['email'], $data['code']);
        return response()->json(['success' => true, 'data' => $result]);
    }

    public function resendEmailOtp(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        $this->authService->resendEmailOtp($request->email);
        return response()->json(['success' => true, 'message' => 'Code renvoyé avec succès.']);
    }

    public function verifyWhatsappOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telephone' => 'required|string|max:20',
            'code' => 'required|string|size:6|regex:/^[0-9]{6}$/',
        ]);

        $result = $this->authService->verifyWhatsappOtp($data['telephone'], $data['code']);
        return response()->json(['success' => true, 'data' => $result]);
    }

    public function resendWhatsappOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['telephone' => 'required|string|max:20']);
        $this->authService->resendWhatsappOtp($data['telephone']);
        return response()->json(['success' => true, 'message' => 'Un nouveau code a été envoyé sur WhatsApp.']);
    }

    public function forgotPasswordWhatsapp(Request $request): JsonResponse
    {
        $data = $request->validate(['telephone' => 'required|string|max:20']);
        $this->authService->sendPasswordResetWhatsappOtp($data['telephone']);
        return response()->json([
            'success' => true,
            'message' => 'Si ce numéro correspond à un compte, un code a été envoyé sur WhatsApp.',
        ]);
    }

    public function resetPasswordWhatsapp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telephone' => 'required|string|max:20',
            'code' => 'required|string|size:6|regex:/^[0-9]{6}$/',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $this->authService->resetPasswordWithWhatsappOtp($data['telephone'], $data['code'], $data['password']);
        return response()->json(['success' => true, 'message' => 'Votre mot de passe a été modifié.']);
    }

    // ── Profil ────────────────────────────────────────────────
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('partner');
        $careerProfile = null;
        if ($user->profile_chosen) {
            $profileChoice = $user->profileChoice()->with('profile')->first();
            $careerProfile  = $profileChoice?->profile;
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'user'                    => $user,
                'career_profile'          => $careerProfile,
                'needs_completion'        => !$user->isProfileComplete(),
                'needs_whatsapp_verification' => !$user->whatsapp_verified && !empty($user->telephone),
                'requires_correspondence' => !$user->correspondence_completed,
                'requires_profile_choice' => $user->correspondence_completed && !$user->profile_chosen,
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'       => 'sometimes|string|max:255',
            'prenom'    => 'sometimes|string|max:255',
            'email'     => 'sometimes|nullable|email|unique:elite_users,email,' . $request->user()->id,
            'ville'     => 'sometimes|string|max:255',
            'password'  => 'sometimes|string|min:6|confirmed',
            'photo_url' => 'sometimes|nullable|string',
        ]);

        $user = $this->authService->updateProfile($request->user(), $data);
        $freshUser = $user->fresh();
        $freshUser->load('partner');
        return response()->json(['success' => true, 'data' => ['user' => $freshUser]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());
        return response()->json(['success' => true, 'message' => 'Déconnexion réussie']);
    }

    public function checkReferralCode(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);
        $result = $this->authService->checkReferralCode($request->code);
        return response()->json(['success' => true, 'data' => $result]);
    }

    // ── Suppression de compte ─────────────────────────────────
    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authService->logout($user);
        $user->delete();
        return response()->json(['success' => true, 'message' => 'Compte supprimé avec succès']);
    }
}