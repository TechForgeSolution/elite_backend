<?php

namespace App\Services;

use App\Models\Lesson;
use Illuminate\Support\Facades\URL;

class CourseVideoUrl
{
    public function __construct(private readonly DirectVideoUrl $resolver)
    {
    }

    public function playback(Lesson $lesson, string $part, ?string $source): ?string
    {
        if (! $source || $this->resolver->driveId($source) === null) {
            return $source;
        }

        $relative = URL::signedRoute('public.lesson-video', [
            'lesson' => $lesson,
            'part' => $part,
        ], absolute: false);

        return rtrim((string) config('app.url'), '/').$relative;
    }
}
