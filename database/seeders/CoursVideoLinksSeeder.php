<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\Pack;
use App\Services\DirectVideoUrl;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class CoursVideoLinksSeeder extends Seeder
{
    public bool $dryRun = false;

    public array $report = [];

    public function run(): void
    {
        File::ensureDirectoryExists(storage_path('app/private'));
        $lock = fopen(storage_path('app/private/cours-import.lock'), 'c');
        if ($lock === false) {
            throw new RuntimeException('Verrou de cours inaccessible.');
        }
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('Un import/correcteur de cours est déjà en cours.');
        }
        try {
            $this->correct();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function correct(): void
    {
        $catalog = json_decode(File::get(public_path('cours.json')), true, 512, JSON_THROW_ON_ERROR);
        Validator::make($catalog, [
            'filieres' => 'required|array', 'filieres.*.nom' => 'required|string',
            'filieres.*.modules' => 'required|array', 'filieres.*.modules.*.numero' => 'required|integer|min:1',
            'filieres.*.modules.*.lecons' => 'present|array',
            'filieres.*.modules.*.lecons.*.numero' => 'required|integer|min:1',
        ])->validate();
        if (DB::getDriverName() === 'mysql') {
            $engine = DB::selectOne("SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'lessons'", [DB::getDatabaseName()]);
            if (strtolower($engine->engine ?? '') !== 'innodb') {
                throw new RuntimeException('La table lessons doit utiliser InnoDB.');
            }
        }
        $resolver = new DirectVideoUrl;
        $this->report = ['lessons_changed' => 0, 'urls_changed' => 0, 'custom_or_empty_preserved' => 0, 'missing_lessons' => 0];
        DB::transaction(function () use ($catalog, $resolver): void {
            $changes = [];
            $seen = [];
            foreach ($catalog['filieres'] as $filiere) {
                $slug = Str::slug($filiere['nom']);
                if (in_array($slug, ['digital', 'developpement-web-et-app', 'marketing-digital'], true)) {
                    throw new RuntimeException('Digital est protégé et ne doit pas figurer dans ce catalogue.');
                }
                $packs = Pack::where('slug', $slug)->get();
                if ($packs->count() > 1) {
                    throw new RuntimeException("Pack ambigu : {$slug}");
                }
                foreach ($filiere['modules'] as $moduleData) {
                    $modules = $packs->isEmpty() ? collect() : Module::where('pack_id', $packs->first()->id)->where('ordre', $moduleData['numero'])->get();
                    if ($modules->count() > 1) {
                        throw new RuntimeException("Module ambigu : {$slug}/{$moduleData['numero']}");
                    }
                    foreach ($moduleData['lecons'] as $sourceLesson) {
                        $key = $slug.'/'.$moduleData['numero'].'/'.$sourceLesson['numero'];
                        if (isset($seen[$key])) {
                            throw new RuntimeException("Leçon dupliquée dans le catalogue : {$key}");
                        }
                        $seen[$key] = true;
                        $lessons = $modules->isEmpty() ? collect() : Lesson::where('module_id', $modules->first()->id)
                            ->where('ordre', $sourceLesson['numero'])->lockForUpdate()->get();
                        if ($lessons->count() > 1) {
                            throw new RuntimeException("Leçon ambiguë : {$key}");
                        }
                        if ($lessons->isEmpty()) {
                            $this->report['missing_lessons']++;
                            continue;
                        }
                        $lesson = $lessons->first();
                        $before = [];
                        $after = [];
                        foreach (['url_video_explication' => 'explications', 'url_video_pratique' => 'cours_pratique', 'url_video' => 'cours_pratique'] as $field => $sourceField) {
                            $direct = $resolver->fromFile($sourceLesson[$sourceField] ?? null);
                            $current = $lesson->{$field};
                            if ($direct === null || $direct === $current) {
                                continue;
                            }
                            // Correct only the same Drive file, never custom URLs or a different video.
                            $expectedId = $resolver->driveId($direct);
                            if ($expectedId === null || $resolver->driveId($current) !== $expectedId) {
                                $this->report['custom_or_empty_preserved']++;
                                continue;
                            }
                            $before[$field] = $current;
                            $after[$field] = $direct;
                        }
                        if ($after) {
                            $changes[] = ['lesson_id' => $lesson->id, 'key' => $key, 'before' => $before, 'after' => $after];
                            $this->report['lessons_changed']++;
                            $this->report['urls_changed'] += count($after);
                        }
                    }
                }
            }
            if (! $this->dryRun && $changes) {
                // Keep an audit of the exact old values before the transaction is committed.
                $path = storage_path('app/private/video-links-'.date('Ymd-His').'-'.bin2hex(random_bytes(6)).'.json');
                $handle = fopen($path, 'x');
                if ($handle === false) {
                    throw new RuntimeException('Impossible de créer la sauvegarde des liens vidéo.');
                }
                try {
                    if (! chmod($path, 0600)) {
                        throw new RuntimeException('Impossible de protéger la sauvegarde des liens vidéo.');
                    }
                    $json = json_encode(['created_at' => now()->toIso8601String(), 'changes' => $changes], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                    if (fwrite($handle, $json) !== strlen($json) || ! fflush($handle)) {
                        throw new RuntimeException('Écriture incomplète de la sauvegarde des liens vidéo.');
                    }
                } finally {
                    fclose($handle);
                }
                $this->report['backup'] = $path;
                foreach ($changes as $change) {
                    // Only these three URL columns; timestamps and all other data stay intact.
                    DB::table('lessons')->where('id', $change['lesson_id'])->update($change['after']);
                }
            }
        });
        $this->command?->info(($this->dryRun ? 'Simulation vidéo : ' : 'Correction vidéo terminée : ').json_encode($this->report, JSON_UNESCAPED_UNICODE));
    }
}
