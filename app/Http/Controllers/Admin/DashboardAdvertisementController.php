<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DashboardAdvertisement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DashboardAdvertisementController extends Controller
{
    public function index()
    {
        $advertisements = DashboardAdvertisement::latest('id')->paginate(20);
        return view('admin.advertisements.index', compact('advertisements'));
    }

    public function create()
    {
        return view('admin.advertisements.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'banner' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192',
            'redirect_url' => 'required|url|max:2048',
        ]);

        DB::transaction(function () use ($request, $validated) {
            DashboardAdvertisement::where('active', true)->update(['active' => false]);
            DashboardAdvertisement::create([
                'title' => $validated['title'] ?? null,
                'banner_path' => $request->file('banner')->store('advertisements', 'public'),
                'redirect_url' => $validated['redirect_url'],
                'active' => true,
            ]);
        });

        return redirect()->route('admin.advertisements.index')->with('success', 'Nouvelle publicité activée.');
    }

    public function deactivate(DashboardAdvertisement $advertisement)
    {
        $advertisement->update(['active' => false]);
        return back()->with('success', 'Publicité désactivée.');
    }

    public function destroy(DashboardAdvertisement $advertisement)
    {
        Storage::disk('public')->delete($advertisement->banner_path);
        $advertisement->delete();
        return back()->with('success', 'Publicité supprimée.');
    }
}
