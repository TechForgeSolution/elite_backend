<?php

namespace Tests\Feature;

use App\Models\DashboardAdvertisement;
use App\Models\EliteUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardAdvertisementTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_advertisement_is_returned_only_to_authenticated_dashboard_clients(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('advertisements/banner.png', 'image');
        $advertisement = DashboardAdvertisement::create([
            'title' => 'Annonce test',
            'banner_path' => 'advertisements/banner.png',
            'redirect_url' => 'https://example.com',
            'active' => true,
        ]);

        $this->getJson('/api/dashboard/advertisement')->assertUnauthorized();

        $user = EliteUser::create([
            'nom' => 'Doe',
            'prenom' => 'Jane',
            'telephone' => '690000008',
            'dernier_diplome' => 'secondaire',
            'ville' => 'Douala',
            'password' => bcrypt('password123'),
            'referral_code' => 'ELITE-AD-TEST',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/advertisement')
            ->assertOk()
            ->assertJsonPath('data.id', $advertisement->id)
            ->assertJsonPath('data.title', 'Annonce test')
            ->assertJsonPath('data.redirect_url', 'https://example.com');

        $advertisement->update(['active' => false]);
        $this->getJson('/api/dashboard/advertisement')
            ->assertOk()
            ->assertJsonPath('data', null);
    }
}
