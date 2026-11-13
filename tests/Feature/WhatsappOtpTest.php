<?php

namespace Tests\Feature;

use App\Models\EliteUser;
use App\Models\SystemSetting;
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_and_verification_require_a_carey_whatsapp_otp(): void
    {
        config(['services.carey.api_key' => 'carey-test-key']);
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/otp/send')) {
                $this->assertSame('237690000007', $request->data()['to']);
                return Http::response(['id' => 'otp-registration-1', 'status' => 'sent'], 200);
            }

            return Http::response(['id' => 'otp-registration-1', 'verified' => true], 200);
        });

        $registration = $this->postJson('/api/auth/register', [
            'nom' => 'Test',
            'prenom' => 'Utilisateur',
            'telephone' => '690000007',
            'email' => 'ignored@example.com',
            'dernier_diplome' => 'secondaire',
            'ville' => 'Douala',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'referral_code' => SystemSetting::getDefaultReferralCode(),
        ]);

        $registration->assertOk()
            ->assertJsonPath('data.needs_otp', true)
            ->assertJsonPath('data.telephone', '690000007');

        $user = EliteUser::where('telephone', '690000007')->firstOrFail();
        $this->assertNull($user->email);
        $this->assertFalse($user->whatsapp_verified);
        $this->assertDatabaseHas('elite_users', [
            'id' => $user->id,
            'whatsapp_otp_id' => 'otp-registration-1',
            'whatsapp_otp_purpose' => 'registration',
        ]);

        $this->postJson('/api/auth/verify-whatsapp-otp', [
            'telephone' => '690000007',
            'code' => '123456',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.whatsapp_verified', true);

        $this->assertDatabaseHas('elite_users', [
            'id' => $user->id,
            'whatsapp_verified' => true,
            'whatsapp_otp_id' => null,
        ]);

        $this->postJson('/api/auth/forgot-password-whatsapp', [
            'telephone' => '690000007',
        ])->assertOk();

        $this->assertDatabaseHas('elite_users', [
            'id' => $user->id,
            'whatsapp_otp_purpose' => 'password_reset',
        ]);

        $this->postJson('/api/auth/reset-password-whatsapp', [
            'telephone' => '690000007',
            'code' => '123456',
            'password' => 'new-secret123',
            'password_confirmation' => 'new-secret123',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret123', $user->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNull($user->whatsapp_otp_id);
    }

    public function test_registration_rejects_an_email_only_account(): void
    {
        $this->postJson('/api/auth/register', [
            'nom' => 'Test',
            'prenom' => 'Utilisateur',
            'email' => 'test@example.com',
            'dernier_diplome' => 'secondaire',
            'ville' => 'Douala',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'referral_code' => SystemSetting::getDefaultReferralCode(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('telephone');
    }

    public function test_social_login_cannot_create_accounts_or_use_google(): void
    {
        $this->mock(FirebaseService::class)
            ->shouldReceive('verifyToken')
            ->twice()
            ->with('firebase-test-token')
            ->andReturn(
                [
                    'localId' => 'firebase-google-user',
                    'email' => 'social@example.com',
                    'providerUserInfo' => [['providerId' => 'google.com']],
                ],
                [
                    'localId' => 'firebase-apple-user',
                    'email' => 'social@example.com',
                    'providerUserInfo' => [['providerId' => 'apple.com']],
                ]
            );

        $this->postJson('/api/auth/social-login', [
            'firebase_token' => 'firebase-test-token',
            'provider' => 'google',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('provider');

        $this->postJson('/api/auth/social-login', [
            'firebase_token' => 'firebase-test-token',
            'provider' => 'apple',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('provider');

        $this->postJson('/api/auth/social-login', [
            'firebase_token' => 'firebase-test-token',
            'provider' => 'apple',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('provider');

        $this->assertDatabaseCount('elite_users', 0);
    }

    public function test_web_registration_uses_whatsapp_otp_before_activation(): void
    {
        config(['services.carey.api_key' => 'carey-test-key']);
        $this->get(route('web.register'))
            ->assertOk()
            ->assertSee('Une question à la fois')
            ->assertSee('C’est presque fini')
            ->assertDontSee('Google')
            ->assertDontSee('name="email"');
        $this->get('/register/auth/google')->assertNotFound();

        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/otp/send')) {
                $this->assertSame('237690000008', $request->data()['to']);
                return Http::response(['id' => 'otp-web-1', 'status' => 'sent'], 200);
            }

            return Http::response(['id' => 'otp-web-1', 'verified' => true], 200);
        });

        $registration = $this->post(route('web.register.submit'), [
            'nom' => 'Test',
            'prenom' => 'Web',
            'telephone' => '+237 690000008',
            'email' => 'ignored@example.com',
            'dernier_diplome' => 'BAC',
            'ville' => 'Douala',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'referral_code' => SystemSetting::getDefaultReferralCode(),
        ]);

        $registration->assertRedirect(route('web.verify-whatsapp'));
        $user = EliteUser::where('telephone', '690000008')->firstOrFail();
        $this->assertFalse($user->whatsapp_verified);
        $this->assertNull($user->email);

        $this->post(route('web.verify-whatsapp.submit'), ['code' => '123456'])
            ->assertOk()
            ->assertSee('Compte créé !');

        $this->assertTrue($user->fresh()->whatsapp_verified);
        $this->assertSame(0, $user->tokens()->count());
    }
}
