<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pack;
use App\Models\Category;
use App\Models\CareerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PackController extends Controller
{
    public function index()
    {
        $packs = Pack::with('category')->withCount(['modules', 'userPacks'])->orderBy('ordre')->paginate(20);
        return view('admin.packs.index', compact('packs'));
    }

    public function create()
    {
        $categories = Category::orderBy('ordre')->get();
        $profiles = CareerProfile::active()->orderBy('nom')->get();
        return view('admin.packs.create', compact('categories', 'profiles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'nom' => 'required|string|max:255',
            'description' => 'nullable|string',
            'banner' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192',
            'niveau_requis' => 'required|string|in:BEPC,Probatoire,BAC,Licence,Master',
            'durees_disponibles' => 'required|array|min:1',
            'diplomes_possibles' => 'nullable|array',
            'prix_points' => 'required|integer|min:1',
            'prix_fcfa' => 'nullable|numeric|min:0',
            'debouches' => 'nullable|array',
            'profiles' => 'nullable|array',
            'active' => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['nom']);
        $validated['active'] = $request->boolean('active');
        unset($validated['banner']);
        if ($request->hasFile('banner')) {
            $validated['banner_path'] = $request->file('banner')->store('packs', 'public');
        }
        $validated['diplomes_possibles'] = $request->input('diplomes_possibles', []);
        if (isset($validated['debouches']) && is_array($validated['debouches'])) {
            $validated['debouches'] = array_values(array_filter(array_map('trim', $validated['debouches'])));
        }

        $pack = Pack::create($validated);

        if ($request->filled('profiles')) {
            $pack->profiles()->sync($request->profiles);
        }

        return redirect()->route('admin.packs.index')->with('success', 'Pack créé avec succès');
    }

    public function show(Pack $pack)
    {
        $pack->load([
            'category',
            'modules.lessons', 
            'modules.quizzes.questions',
            'profiles', 
            'userPacks.user'
        ]);
        return view('admin.packs.show', compact('pack'));
    }

    public function edit(Pack $pack)
    {
        $categories = Category::orderBy('ordre')->get();
        $profiles = CareerProfile::active()->orderBy('nom')->get();
        $pack->load('profiles');
        return view('admin.packs.edit', compact('pack', 'categories', 'profiles'));
    }

    public function update(Request $request, Pack $pack)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'nom' => 'required|string|max:255',
            'description' => 'nullable|string',
            'banner' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192',
            'remove_banner' => 'nullable|boolean',
            'niveau_requis' => 'required|string|in:BEPC,Probatoire,BAC,Licence,Master',
            'durees_disponibles' => 'required|array|min:1',
            'diplomes_possibles' => 'nullable|array',
            'prix_points' => 'required|integer|min:1',
            'prix_fcfa' => 'nullable|numeric|min:0',
            'debouches' => 'nullable|array',
            'profiles' => 'nullable|array',
            'active' => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['nom']);
        $validated['active'] = $request->boolean('active');
        unset($validated['banner'], $validated['remove_banner']);
        if ($request->boolean('remove_banner')) {
            if ($pack->banner_path) {
                Storage::disk('public')->delete($pack->banner_path);
            }
            $validated['banner_path'] = null;
        } elseif ($request->hasFile('banner')) {
            if ($pack->banner_path) {
                Storage::disk('public')->delete($pack->banner_path);
            }
            $validated['banner_path'] = $request->file('banner')->store('packs', 'public');
        }
        $validated['diplomes_possibles'] = $request->input('diplomes_possibles', []);
        if (isset($validated['debouches']) && is_array($validated['debouches'])) {
            $validated['debouches'] = array_values(array_filter(array_map('trim', $validated['debouches'])));
        }

        $pack->update($validated);

        if ($request->filled('profiles')) {
            $pack->profiles()->sync($request->profiles);
        } else {
            $pack->profiles()->detach();
        }

        return redirect()->route('admin.packs.show', $pack)->with('success', 'Pack mis à jour');
    }

    public function destroy(Pack $pack)
    {
        $pack->delete();
        return redirect()->route('admin.packs.index')->with('success', 'Pack supprimé');
    }

    public function toggleActive(Pack $pack)
    {
        $pack->update(['active' => !$pack->active]);
        return back()->with('success', 'Statut du pack modifié');
    }
}
