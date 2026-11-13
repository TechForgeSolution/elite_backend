<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\EliteUser;
use App\Models\SystemSetting;
use App\Services\AuthService;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    /**
     * Afficher la page d'inscription web
     * Le code de parrainage peut être passé en paramètre ?ref=CODE
     */
    public function showRegisterForm(Request $request)
    {
        $referralCode = $request->query('ref', '');
        $parrainName  = null;

        if ($referralCode) {
            $parrain = EliteUser::where('referral_code', $referralCode)->first();
            if ($parrain) {
                $parrainName = $parrain->prenom . ' ' . $parrain->nom;
            }
        }

        return view('web.register', compact('referralCode', 'parrainName'));
    }

    /**
     * Traiter l'inscription web
     */
    public function register(Request $request, AuthService $authService)
    {
        $telephone = preg_replace('/\D+/', '', (string) $request->input('telephone'));
        if (str_starts_with($telephone, '237') && strlen($telephone) === 12) {
            $telephone = substr($telephone, 3);
        }
        $request->merge(['telephone' => $telephone]);

        $data = $request->validate([
            'nom'                  => 'required|string|max:255',
            'prenom'               => 'required|string|max:255',
            'telephone'            => ['required', 'string', 'regex:/^6\d{8}$/', 'unique:elite_users,telephone'],
            'dernier_diplome'      => 'required|string|max:255',
            'ville'                => 'required|string|max:255',
            'password'             => 'required|string|min:6|confirmed',
            'referral_code'        => 'nullable|string|max:50',
        ]);

        $data['referral_code'] = $data['referral_code'] ?: SystemSetting::getDefaultReferralCode();

        $authService->register($data);
        $request->session()->put('registration_telephone', $data['telephone']);

        return redirect()->route('web.verify-whatsapp');
    }

    public function showWhatsappVerification(Request $request)
    {
        abort_unless($request->session()->has('registration_telephone'), 403);

        return view('web.whatsapp_otp', [
            'telephone' => $request->session()->get('registration_telephone'),
        ]);
    }

    public function verifyWhatsappOtp(Request $request, AuthService $authService)
    {
        abort_unless($request->session()->has('registration_telephone'), 403);
        $data = $request->validate(['code' => 'required|string|size:6|regex:/^[0-9]{6}$/']);
        $telephone = $request->session()->get('registration_telephone');

        $authService->verifyWhatsappOtp($telephone, $data['code'], false);
        $request->session()->forget('registration_telephone');

        $user = EliteUser::where('telephone', $telephone)->firstOrFail();
        $userAgent = $request->header('User-Agent', '');
        $storeUrl = stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false
            ? 'https://apps.apple.com/app/elite-20/id0000000000'
            : 'https://play.google.com/store/apps/details?id=com.ghost777xsorganization.elite20';

        return view('web.register_success', compact('user', 'storeUrl'));
    }

    public function resendWhatsappOtp(Request $request, AuthService $authService)
    {
        abort_unless($request->session()->has('registration_telephone'), 403);
        $authService->resendWhatsappOtp($request->session()->get('registration_telephone'));

        return back()->with('status', 'Un nouveau code a été envoyé sur WhatsApp.');
    }
}