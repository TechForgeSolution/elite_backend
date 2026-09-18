<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Pack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CourseVideoStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_drive_video_is_relayed_with_byte_ranges(): void
    {
        $lesson = $this->driveLesson();
        Http::fake([
            'drive.usercontent.google.com/*' => Http::response('video-bytes', 206, [
                'Content-Type' => 'video/mp4',
                'Content-Length' => '11',
                'Content-Range' => 'bytes 0-10/500000000',
                'Accept-Ranges' => 'bytes',
            ]),
        ]);

        $url = URL::signedRoute('public.lesson-video', [
            'lesson' => $lesson,
            'part' => 'explication',
        ], absolute: false);

        $this->withHeader('Range', 'bytes=0-10')->get($url)
            ->assertStatus(206)
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('Content-Range', 'bytes 0-10/500000000')
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Content-Disposition', 'inline');

        Http::assertSent(function (Request $request): bool {
            return str_starts_with($request->url(), 'https://drive.usercontent.google.com/download?')
                && $request->hasHeader('Range', 'bytes=0-10')
                && ! $request->hasHeader('Sec-Fetch-Dest');
        });
    }

    public function test_unsigned_and_invalid_range_requests_are_rejected(): void
    {
        $lesson = $this->driveLesson();

        $this->get("/api/public/lessons/{$lesson->id}/video/explication")
            ->assertForbidden();

        $url = URL::signedRoute('public.lesson-video', [
            'lesson' => $lesson,
            'part' => 'explication',
        ], absolute: false);
        $this->withHeader('Range', 'bytes=0-10,20-30')->get($url)
            ->assertStatus(416);
    }

    private function driveLesson(): Lesson
    {
        $category = Category::create([
            'nom' => 'Test', 'slug' => 'test-video', 'ordre' => 1, 'active' => true,
        ]);
        $pack = Pack::create([
            'category_id' => $category->id, 'nom' => 'Test', 'slug' => 'test-video',
            'description' => 'Pack vidéo de test',
            'niveau_requis' => 'BAC', 'durees_disponibles' => ['6_mois'],
            'prix_points' => 1, 'prix_reel_fcfa' => 1, 'active' => true,
        ]);
        $module = Module::create([
            'pack_id' => $pack->id, 'nom' => 'Test', 'type' => 'theorique',
            'ordre' => 1, 'active' => true,
        ]);

        return Lesson::create([
            'module_id' => $module->id, 'titre' => 'Vidéo test',
            'url_video' => 'https://drive.usercontent.google.com/download?id=18vahsjNrwCrx-AvEpTpR3i8qnTJQ6eax&export=download&confirm=t',
            'url_video_explication' => 'https://drive.google.com/file/d/18vahsjNrwCrx-AvEpTpR3i8qnTJQ6eax/view',
            'duree_minutes' => 1, 'ordre' => 1, 'active' => true,
        ]);
    }
}
