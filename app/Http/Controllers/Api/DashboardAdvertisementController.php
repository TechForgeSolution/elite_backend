<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DashboardAdvertisement;
use Illuminate\Http\JsonResponse;

class DashboardAdvertisementController extends Controller
{
    public function active(): JsonResponse
    {
        $advertisement = DashboardAdvertisement::query()
            ->where('active', true)
            ->latest('id')
            ->first();

        if (!$advertisement) {
            return response()->json(['success' => true, 'data' => null]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $advertisement->id,
                'title' => $advertisement->title,
                'banner_url' => $advertisement->banner_url,
                'redirect_url' => $advertisement->redirect_url,
            ],
        ]);
    }
}
