<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Pack;
use App\Models\Quiz;
use App\Services\TheoryCourseCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class CoursJsonSeeder extends Seeder
{
    public bool $dryRun = false;

    public bool $offline = false;

    public array $report = [];

    public function run(): void
    {
        File::ensureDirectoryExists(storage_path('app/private'));
        $lock = fopen(storage_path('app/private/cours-import.lock'), 'c');
        if ($lock === false) {
            throw new RuntimeException('Impossible de créer le verrou de l’import. Vérifier les permissions de storage.');
        }
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('Un import de cours est déjà en cours sur ce serveur.');
        }
        try {
            $this->import();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function import(): void
    {
        $this->report = ['created' => [], 'preserved' => []];
        foreach (['categories' => ['slug'], 'packs' => ['slug', 'category_id'],
            'modules' => ['pack_id', 'ordre'], 'lessons' => ['module_id', 'url_web', 'url_video_explication', 'url_video_pratique'],
            'quizzes' => ['module_id'], 'quiz_questions' => ['quiz_id'], 'quiz_answers' => ['question_id']] as $table => $columns) {
            if (! Schema::hasColumns($table, $columns)) {
                throw new RuntimeException("Schéma incomplet : {$table}. Vérifier les migrations avant l’import.");
            }
        }
        if (DB::getDriverName() === 'mysql') {
            $tables = DB::select('SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [DB::getDatabaseName()]);
            foreach ($tables as $table) {
                if (in_array($table->name, ['categories', 'packs', 'modules', 'lessons', 'quizzes', 'quiz_questions', 'quiz_answers'], true)
                    && strtolower($table->engine ?? '') !== 'innodb') {
                    throw new RuntimeException("La table {$table->name} doit utiliser InnoDB pour garantir l’annulation de l’import.");
                }
            }
        }
        $catalog = json_decode(File::get(public_path('cours.json')), true, 512, JSON_THROW_ON_ERROR);
        Validator::make($catalog, [
            'filieres' => 'required|array|min:1',
            'filieres.*.nom' => 'required|string|max:255',
            'filieres.*.modules' => 'required|array|min:1',
            'filieres.*.modules.*.numero' => 'required|integer|min:1',
            'filieres.*.modules.*.nom' => 'required|string',
            'filieres.*.modules.*.lecons' => 'present|array',
            'filieres.*.modules.*.lecons.*.numero' => 'required|integer|min:1',
            'filieres.*.modules.*.lecons.*.nom' => 'required|string|max:255',
        ])->validate();

        $theory = new TheoryCourseCatalog;
        $theoryUrls = $theory->urls();
        $slugs = array_map(fn ($filiere) => Str::slug($filiere['nom']), $catalog['filieres']);
        if (in_array('', $slugs, true) || count($slugs) !== count(array_unique($slugs))) {
            throw new RuntimeException('Slugs de filières vides ou dupliqués.');
        }
        if (array_intersect($slugs, ['digital', 'developpement-web-et-app', 'marketing-digital'])) {
            throw new RuntimeException('Le parcours Digital est protégé : il ne doit pas être inclus dans cet import.');
        }

        // Download and validate everything before modifying the database.
        $quizzes = [];
        $withoutTheory = 0;
        $withoutQuiz = 0;
        $catalogKeys = [];
        foreach ($catalog['filieres'] as $filiere) {
            $numbers = array_column($filiere['modules'], 'numero');
            if (count($numbers) !== count(array_unique($numbers))) {
                throw new RuntimeException("Numéros de modules dupliqués : {$filiere['nom']}");
            }
            foreach ($filiere['modules'] as $module) {
                foreach ($module['lecons'] as $lesson) {
                    $key = $theory->key(Str::slug($filiere['nom']), $module['numero'], $lesson['numero']);
                    $catalogKeys[$key] = true;
                    if (! isset($theoryUrls[$key])) {
                        $withoutTheory++;
                    }
                    if (! empty($lesson['cours_theorique']) && ! isset($theoryUrls[$key])) {
                        throw new RuntimeException("Correspondance HTML absente : {$key}");
                    }
                }
                $lessons = array_column($module['lecons'], 'numero');
                if (count($lessons) !== count(array_unique($lessons))) {
                    throw new RuntimeException("Numéros de leçons dupliqués : {$filiere['nom']}/{$module['numero']}");
                }
                if (! empty($module['quiz'])) {
                    $id = $this->driveId($module['quiz']);
                    $quizzes[$id] ??= $this->loadQuiz($id);
                } else {
                    $withoutQuiz++;
                    $this->command?->warn("Sans quiz : {$filiere['nom']} / module {$module['numero']}");
                }
            }
        }

        if (array_diff_key($theoryUrls, $catalogKeys)) {
            throw new RuntimeException('Le manifeste HTML contient des leçons absentes du catalogue.');
        }
        $this->report['source'] = ['html' => count($theoryUrls), 'quizzes' => count($quizzes),
            'lessons_without_html' => $withoutTheory, 'modules_without_quiz' => $withoutQuiz];

        $counts = ['filieres' => 0, 'modules' => 0, 'lecons' => 0, 'quiz' => 0, 'questions' => 0];
        $transactionLevel = DB::transactionLevel();
        DB::beginTransaction();
        try {
            foreach ($catalog['filieres'] as $index => $filiere) {
                $slug = Str::slug($filiere['nom']);
                $category = $this->preserveOrCreate(Category::class, ['slug' => $slug], [
                    'nom' => $filiere['nom'], 'ordre' => $index + 1, 'active' => true,
                ]);
                // One complete curriculum per source filiere, without guessing mappings
                // to the narrower training packs created by other seeders.
                $pack = $this->preserveOrCreate(Pack::class, ['slug' => $slug], [
                    'category_id' => $category->id, 'nom' => $filiere['nom'],
                    'description' => 'Formation '.$filiere['nom'], 'niveau_requis' => 'BEPC',
                    'durees_disponibles' => [], 'ordre' => $index + 1, 'active' => true,
                ]);
                $counts['filieres']++;
                foreach ($filiere['modules'] as $moduleData) {
                    $data = empty($moduleData['quiz']) ? null : $quizzes[$this->driveId($moduleData['quiz'])];
                    $title = $data['module']['titre'] ?? $data['module']['title'] ?? $moduleData['nom'];
                    $module = $this->preserveOrCreate(Module::class, ['pack_id' => $pack->id, 'ordre' => $moduleData['numero']], [
                        'nom' => Str::limit('Module '.$moduleData['numero'].' - '.$title, 255, ''),
                        'description' => $data['consignes'] ?? null, 'type' => 'theorique', 'active' => true,
                    ]);
                    $counts['modules']++;
                    foreach ($moduleData['lecons'] as $lesson) {
                        $record = $this->preserveOrCreate(Lesson::class, ['module_id' => $module->id, 'ordre' => $lesson['numero']], [
                            'titre' => $lesson['nom'],
                            'url_web' => $theoryUrls[$theory->key($slug, $moduleData['numero'], $lesson['numero'])] ?? null,
                            'url_video_explication' => $this->resourceUrl($lesson['explications'] ?? null),
                            'url_video_pratique' => $this->resourceUrl($lesson['cours_pratique'] ?? null),
                            'url_video' => $this->resourceUrl($lesson['cours_pratique'] ?? null),
                            'active' => true,
                        ]);
                        $expectedUrl = $theoryUrls[$theory->key($slug, $moduleData['numero'], $lesson['numero'])] ?? null;
                        if (! $record->wasRecentlyCreated && $expectedUrl && $record->url_web !== $expectedUrl) {
                            $this->report['preserved_different_theory_urls'][] = $record->id;
                        }
                        $counts['lecons']++;
                    }
                    if ($data === null) {
                        continue;
                    }
                    // An existing quiz is indivisible: keep questions, answer IDs,
                    // order, corrections and results intact, including custom quizzes.
                    if ($module->quizzes()->exists()) {
                        $this->report['preserved']['quizzes'] = ($this->report['preserved']['quizzes'] ?? 0) + $module->quizzes()->count();
                        continue;
                    }
                    $quiz = $this->preserveOrCreate(Quiz::class, ['module_id' => $module->id, 'ordre' => 1], [
                        'titre' => Str::limit('Quiz - '.$title, 255, ''),
                        'description' => $data['consignes'] ?? null,
                        'note_totale' => 20, 'duree_minutes' => 30, 'active' => true,
                    ]);
                    $counts['quiz']++;
                    foreach (array_values($data['questions']) as $i => $item) {
                        $letters = $this->answerOrder();
                        $displayLetters = array_combine($letters, ['A', 'B', 'C', 'D']);
                        $explanation = $item['explication'] ?? null;
                        if (is_array($explanation)) {
                            $lines = [$explanation['pourquoi_correcte'] ?? ''];
                            foreach ($explanation['pourquoi_incorrectes'] ?? [] as $letter => $reason) {
                                $lines[] = ($displayLetters[$letter] ?? $letter).' : '.$reason;
                            }
                            $explanation = implode("\n", array_filter($lines));
                        }
                        $question = $quiz->questions()->create([
                            'ordre' => $i + 1,
                            'enonce' => $item['question'], 'type' => 'qcm',
                            'explication' => $explanation, 'points' => 2, 'active' => true,
                        ]);
                        foreach ($letters as $j => $letter) {
                            $question->answers()->create([
                                'ordre' => $j + 1,
                                'texte' => $item['options'][$letter],
                                'est_correcte' => $item['bonne_reponse'] === $letter,
                            ]);
                        }
                        $counts['questions']++;
                    }
                }
            }
            if ($this->dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } finally {
            if (DB::transactionLevel() > $transactionLevel) {
                DB::rollBack($transactionLevel);
            }
        }
        $this->report['questions_created'] = $counts['questions'];
        $this->command?->info(($this->dryRun ? 'Simulation annulée, aucune ligne conservée : ' : 'Import terminé : ').json_encode($this->report, JSON_UNESCAPED_UNICODE));
        if ($withoutTheory || $withoutQuiz) {
            $this->command?->warn("Sources incomplètes : {$withoutTheory} leçons sans HTML, {$withoutQuiz} modules sans quiz.");
        }
    }

    private function preserveOrCreate(string $model, array $identity, array $values): Model
    {
        $matches = $model::where($identity)->get();
        if ($matches->count() > 1) {
            throw new RuntimeException('Correspondance ambiguë pour '.$model.' : '.json_encode($identity));
        }
        $existing = $matches->first();
        $record = $existing ?? $model::create(array_merge($identity, $values));
        $action = $existing ? 'preserved' : 'created';
        $table = $record->getTable();
        $this->report[$action][$table] = ($this->report[$action][$table] ?? 0) + 1;

        return $record;
    }

    protected function answerOrder(): array
    {
        $letters = ['A', 'B', 'C', 'D'];
        shuffle($letters);

        return $letters;
    }

    private function driveId(array $file): string
    {
        $id = $file['id'] ?? null;
        if (! $id && preg_match('~/file/d/([\w-]+)~', $file['url'] ?? '', $match)) {
            $id = $match[1];
        }
        if (! is_string($id) || ! preg_match('/^[\w-]+$/', $id)) {
            throw new RuntimeException('Identifiant Google Drive invalide.');
        }

        return $id;
    }

    private function resourceUrl(?array $file): ?string
    {
        return $file ? ($file['url_preview'] ?? $file['url'] ?? null) : null;
    }

    private function loadQuiz(string $id): array
    {
        $path = storage_path('app/private/cours-quizzes/'.$id.'.json');
        $bundle = database_path('data/cours-quizzes/'.$id.'.json');
        if ($this->offline && ! File::exists($bundle) && ! File::exists($path)) {
            throw new RuntimeException("Quiz local absent : {$id}. Déployer database/data/cours-quizzes avant l’import.");
        }
        $this->command?->line('Chargement du quiz '.$id);
        try {
            $content = File::exists($bundle) ? File::get($bundle) : (File::exists($path) ? File::get($path) : Http::connectTimeout(15)
                ->timeout(60)->retry(3, 1000)->get('https://drive.google.com/uc', [
                    'export' => 'download', 'id' => $id,
                ])->throw()->body());
            $data = $this->normalizeQuiz(json_decode($content, true, 512, JSON_THROW_ON_ERROR));
            Validator::make($data, [
                'module' => 'required|array',
                'module.titre' => 'nullable|string',
                'module.title' => 'nullable|string',
                'consignes' => 'nullable|string',
                'questions' => 'required|array|min:1',
                'questions.*.question' => 'required|string',
                'questions.*.options' => 'required|array:A,B,C,D',
                'questions.*.options.A' => 'required|string',
                'questions.*.options.B' => 'required|string',
                'questions.*.options.C' => 'required|string',
                'questions.*.options.D' => 'required|string',
                'questions.*.bonne_reponse' => 'required|in:A,B,C,D',
            ])->validate();
            if (! File::exists($bundle) && ! $this->dryRun) {
                File::ensureDirectoryExists(dirname($path));
                File::put($path, $content);
            }

            return $data;
        } catch (\Throwable $e) {
            throw new RuntimeException("Quiz {$id} inaccessible ou invalide : {$e->getMessage()}", 0, $e);
        }
    }

    private function normalizeQuiz(array $data): array
    {
        if (is_array($data['consignes'] ?? null)) {
            $data['consignes'] = implode("\n", $data['consignes']);
        }
        if (! is_array($data['module'] ?? null)) {
            $data['module'] = ['titre' => $data['titre_quiz'] ?? $data['titre'] ?? (string) ($data['module'] ?? 'Quiz')];
        }
        $corrections = [];
        foreach ($data['correction_et_explications'] ?? $data['Correction et Explications'] ?? [] as $correction) {
            $key = $correction['question_id'] ?? $correction['numero'] ?? $correction['id'] ?? null;
            if ($key === null || isset($corrections[$key])) {
                throw new RuntimeException('Identifiant de correction absent ou dupliqué.');
            }
            $corrections[$key] = $correction;
        }
        if (! is_array($data['questions'] ?? null)) {
            throw new RuntimeException('Liste des questions absente ou invalide.');
        }
        foreach ($data['questions'] as &$question) {
            // Corrections are joined by source ID, never by their position in the file.
            $key = $question['id'] ?? $question['numero'] ?? null;
            $local = $question['correction_et_explications'] ?? null;
            $correction = is_array($local) ? $local : ($corrections[$key] ?? []);
            $question['bonne_reponse'] = $question['bonne_reponse'] ?? $question['reponse_correcte']
                ?? $correction['bonne_reponse'] ?? $correction['reponse_correcte'] ?? null;
            $question['explication'] = $question['explication'] ?? $correction['explication']
                ?? (is_string($local) ? $local : null);
            if (is_array($question['options'] ?? null) && array_is_list($question['options'])) {
                $options = [];
                foreach ($question['options'] as $option) {
                    $letter = $option['id'] ?? null;
                    if ($letter === null || isset($options[$letter])) {
                        throw new RuntimeException('Identifiant de réponse absent ou dupliqué.');
                    }
                    $options[$letter] = $option['texte'] ?? null;
                }
                $question['options'] = $options;
            }
        }
        unset($question);

        return $data;
    }
}
