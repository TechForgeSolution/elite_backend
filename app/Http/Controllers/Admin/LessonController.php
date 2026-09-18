<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Chapter;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Services\DirectVideoUrl;
use App\Services\CourseVideoUrl;

class LessonController extends Controller
{
    public function create(Request $request, $target = null)
    {
        // Support either Module or Chapter
        $module = null;
        if ($target instanceof Module) {
            $module = $target;
        } elseif ($target instanceof Chapter) {
            $module = $target->module;
        } else {
            $moduleId = $request->route('module') ?? $request->route('chapter') ?? $target;
            $module = Module::find($moduleId);
            if (!$module) {
                $chapter = Chapter::find($moduleId);
                $module = $chapter?->module;
            }
        }

        if (!$module) {
            abort(404, 'Module non trouvé');
        }

        return view('admin.lessons.create', compact('module'));
    }

    public function store(Request $request, $target = null)
    {
        $module = null;
        if ($target instanceof Module) {
            $module = $target;
        } elseif ($target instanceof Chapter) {
            $module = $target->module;
        } else {
            $moduleId = $request->route('module') ?? $request->route('chapter') ?? $target;
            $module = Module::find($moduleId);
            if (!$module) {
                $chapter = Chapter::find($moduleId);
                $module = $chapter?->module;
            }
        }

        if (!$module) {
            abort(404, 'Module non trouvé');
        }

        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'contenu_texte' => 'nullable|string',
            'url_web' => 'nullable|url',
            'url_video' => 'nullable|url',
            'url_video_explication' => 'nullable|url',
            'url_video_pratique' => 'nullable|url',
            'url_externe' => 'nullable|url',
            'duree_minutes' => 'required|integer|min:1',
            'ordre' => 'required|integer|min:0',
            'active' => 'boolean',
        ]);

        if (empty($validated['url_web']) && !empty($validated['url_externe'])) {
            $validated['url_web'] = $validated['url_externe'];
        }
        unset($validated['url_externe']);

        if (empty($validated['url_video']) && !empty($validated['url_video_explication'])) {
            $validated['url_video'] = $validated['url_video_explication'];
        } elseif (!empty($validated['url_video']) && empty($validated['url_video_explication'])) {
            $validated['url_video_explication'] = $validated['url_video'];
        }

        $validated['active'] = $request->boolean('active');
        $validated['module_id'] = $module->id;
        
        $lesson = $module->lessons()->create($validated);

        return redirect()->route('admin.packs.show', $module->pack)->with('success', 'Leçon créée avec succès (3 parties pédagogiques intégrées).');
    }

    public function edit(Lesson $lesson)
    {
        $lesson->load('module.pack');
        $module = $lesson->module;
        return view('admin.lessons.edit', compact('lesson', 'module'));
    }

    public function update(Request $request, Lesson $lesson)
    {
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'contenu_texte' => 'nullable|string',
            'url_web' => 'nullable|url',
            'url_video' => 'nullable|url',
            'url_video_explication' => 'nullable|url',
            'url_video_pratique' => 'nullable|url',
            'url_externe' => 'nullable|url',
            'duree_minutes' => 'required|integer|min:1',
            'ordre' => 'required|integer|min:0',
            'active' => 'boolean',
        ]);

        if (empty($validated['url_web']) && !empty($validated['url_externe'])) {
            $validated['url_web'] = $validated['url_externe'];
        }
        unset($validated['url_externe']);

        if (empty($validated['url_video']) && !empty($validated['url_video_explication'])) {
            $validated['url_video'] = $validated['url_video_explication'];
        } elseif (!empty($validated['url_video']) && empty($validated['url_video_explication'])) {
            $validated['url_video_explication'] = $validated['url_video'];
        }

        $validated['active'] = $request->boolean('active');
        $lesson->update($validated);

        $pack = $lesson->module?->pack;
        return $pack 
            ? redirect()->route('admin.packs.show', $pack)->with('success', 'Leçon mise à jour avec succès.')
            : redirect()->back()->with('success', 'Leçon mise à jour avec succès.');
    }

    public function videoPreview(Lesson $lesson, string $part, DirectVideoUrl $resolver, CourseVideoUrl $videoUrl): View|RedirectResponse
    {
        $url = match ($part) {
            'explication' => $lesson->url_video_explication ?: $lesson->url_video,
            'pratique' => $lesson->url_video_pratique ?: $lesson->url_video,
            default => abort(404),
        };
        abort_unless($url, 404);

        $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
        $isDirectMedia = $resolver->driveId($url) !== null
            || in_array($host, ['drive.usercontent.google.com'], true)
            || str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?: ''), '.mp4');

        if (! $isDirectMedia) {
            return redirect()->away($url);
        }

        return view('admin.lessons.video-preview', [
            'lesson' => $lesson,
            'part' => $part,
            'url' => $videoUrl->playback($lesson, $part, $url),
        ]);
    }

    public function destroy(Lesson $lesson)
    {
        $pack = $lesson->module?->pack;
        $lesson->delete();
        return $pack
            ? redirect()->route('admin.packs.show', $pack)->with('success', 'Leçon supprimée avec succès.')
            : redirect()->back()->with('success', 'Leçon supprimée avec succès.');
    }
}
