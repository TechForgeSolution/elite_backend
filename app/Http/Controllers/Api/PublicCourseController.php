<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Pack;
use App\Services\DirectVideoUrl;
use App\Services\CourseVideoUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PublicCourseController extends Controller
{
    public function digital(): JsonResponse
    {
        $pack = Pack::where('slug', 'developpement-web-et-app')
            ->with('modules.chapters.lessons')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $pack->modules->map(fn ($module) => [
                'id' => $module->id,
                'nom' => $module->nom,
                'chapters' => $module->chapters->map(fn ($chapter) => [
                    'id' => $chapter->id,
                    'nom' => $chapter->nom,
                    'lessons' => $chapter->lessons->map(fn ($lesson) => $this->lessonLinks($lesson)),
                ]),
            ]),
        ]);
    }

    public function theory(Lesson $lesson): RedirectResponse
    {
        abort_unless($lesson->active && $lesson->url_web, 404);

        return redirect()->away($lesson->url_web);
    }

    public function video(Request $request, Lesson $lesson, string $part, DirectVideoUrl $resolver)
    {
        $url = match ($part) {
            'explication' => $lesson->url_video_explication ?: $lesson->url_video,
            'pratique' => $lesson->url_video_pratique ?: $lesson->url_video,
            default => null,
        };

        abort_unless($lesson->active && $url, 404);

        $driveId = $resolver->driveId($url);
        if ($driveId === null) {
            return redirect()->away($url);
        }

        $range = $request->header('Range');
        if ($range !== null && ! preg_match('/^bytes=\d*-\d*$/', $range)) {
            return response('', 416, ['Content-Range' => 'bytes */*']);
        }

        $client = Http::connectTimeout(15)->timeout(0)->withOptions([
            'stream' => true,
            'allow_redirects' => ['max' => 5, 'strict' => true, 'referer' => false],
        ]);
        if ($range !== null) {
            $client = $client->withHeader('Range', $range);
        }

        $upstream = $client->get($resolver->forDriveId($driveId));
        abort_unless(in_array($upstream->status(), [200, 206], true), 502, 'La vidÃ©o distante est temporairement indisponible.');

        $headers = array_filter([
            'Content-Type' => $upstream->header('Content-Type') ?: 'video/mp4',
            'Content-Length' => $upstream->header('Content-Length'),
            'Content-Range' => $upstream->header('Content-Range'),
            'Accept-Ranges' => $upstream->header('Accept-Ranges') ?: 'bytes',
            'ETag' => $upstream->header('ETag'),
            'Last-Modified' => $upstream->header('Last-Modified'),
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Expose-Headers' => 'Accept-Ranges, Content-Length, Content-Range',
            'X-Accel-Buffering' => 'no',
        ], fn ($value) => $value !== null && $value !== '');
        $body = $upstream->toPsrResponse()->getBody();

        return response()->stream(function () use ($body): void {
            while (! $body->eof()) {
                echo $body->read(1024 * 256);
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, $upstream->status(), $headers);
    }

    private function lessonLinks(Lesson $lesson): array
    {
        $videoUrl = app(CourseVideoUrl::class);

        return [
            'id' => $lesson->id,
            'titre' => $lesson->titre,
            'theorie_url' => url('/api/public/lessons/' . $lesson->id . '/theory'),
            'video_pratique_url' => $videoUrl->playback($lesson, 'pratique', $lesson->url_video_pratique ?: $lesson->url_video),
            'video_explication_url' => $videoUrl->playback($lesson, 'explication', $lesson->url_video_explication ?: $lesson->url_video),
        ];
    }
}
