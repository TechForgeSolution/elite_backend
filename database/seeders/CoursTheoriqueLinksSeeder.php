<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\Pack;
use App\Services\TheoryCourseCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoursTheoriqueLinksSeeder extends Seeder
{
    public function run(): void
    {
        // Validate all files before updating existing lessons. No quiz import.
        $urls = (new TheoryCourseCatalog)->urls();
        $updated = 0;
        $preserved = 0;
        DB::transaction(function () use ($urls, &$updated, &$preserved): void {
            foreach ($urls as $key => $url) {
                [$slug, $moduleNumber, $lessonNumber] = explode('/', $key);
                $pack = Pack::where('slug', $slug)->sole();
                $module = Module::where('pack_id', $pack->id)->where('ordre', $moduleNumber)->sole();
                $lesson = Lesson::where('module_id', $module->id)->where('ordre', $lessonNumber)->sole();
                if ($lesson->url_web === null || $lesson->url_web === '') {
                    $lesson->update(['url_web' => $url]);
                    $updated++;
                } else {
                    $preserved++;
                }
            }
        });
        $this->command?->info("{$updated} liens vides complétés ; {$preserved} liens existants conservés.");
    }
}
