<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Pack;
use App\Models\Quiz;
use App\Models\Module;
use Illuminate\Support\Facades\DB;
use Database\Seeders\CoursJsonSeeder;
use Database\Seeders\CoursTheoriqueLinksSeeder;
use Database\Seeders\CoursVideoLinksSeeder;
use App\Services\DirectVideoUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CoursJsonSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDirectory = sys_get_temp_dir().'/elite-cours-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->fixtureDirectory);
        $this->app->usePublicPath($this->fixtureDirectory);
        $this->app->useStoragePath($this->fixtureDirectory.'/storage');
        Http::preventStrayRequests();
        File::ensureDirectoryExists(public_path('cours_theorique'));
        File::put(public_path('cours_theorique/audiovisuel__module3__lecon11.html'), '<!DOCTYPE html><html><body>Cours</body></html>');
        File::put(public_path('cours_theorique/liens_cours_theorique.json'), json_encode(['filieres' => [[
            'nom' => 'Audiovisuel', 'modules' => [[
                'numero' => 3, 'lecons' => [[
                    'numero' => 11, 'fichier_regroupe' => 'audiovisuel__module3__lecon11.html',
                ]],
            ]],
        ]]]));
        File::put(public_path('cours.json'), json_encode(['filieres' => [[
            'nom' => 'Audiovisuel', 'modules' => [[
                'numero' => 3, 'nom' => 'Module3', 'quiz' => ['id' => 'quiz-test'],
                'lecons' => [[
                    'numero' => 11, 'nom' => 'Leçon 11',
                    'cours_theorique' => ['url_preview' => 'https://drive.google.com/file/d/theorie/preview'],
                    'explications' => ['url' => 'https://drive.google.com/file/d/video/view'],
                    'cours_pratique' => null,
                ]],
            ], [
                'numero' => 4, 'nom' => 'Module4', 'quiz' => null, 'lecons' => [],
            ]],
        ]]], JSON_UNESCAPED_UNICODE));
    }

    protected function tearDown(): void
    {
        if (isset($this->fixtureDirectory)) {
            File::deleteDirectory($this->fixtureDirectory);
        }
        parent::tearDown();
    }

    public function test_imports_quizzes_and_repeated_import_preserves_ids(): void
    {
        Http::fake(['drive.google.com/*' => Http::response([
            'module' => ['numero' => '3', 'titre' => 'Montage vidéo'],
            'consignes' => 'Choisissez une réponse.',
            'questions' => [[
                'id' => 1, 'question' => 'Quel outil ?',
                'options' => ['A' => 'LUT', 'B' => 'Timeline', 'C' => 'Masque', 'D' => 'Scope'],
                'bonne_reponse' => 'B',
                'explication' => [
                    'pourquoi_correcte' => 'La timeline organise les plans.',
                    'pourquoi_incorrectes' => ['A' => 'Le LUT transforme les couleurs.'],
                ],
            ]],
        ])]);
        $this->seed(CoursJsonSeeder::class);
        $quiz = Quiz::with('questions.answers')->sole();
        $question = $quiz->questions->sole();
        $answerIds = $question->answers->modelKeys();
        $this->assertSame('Timeline', $question->answers->where('est_correcte', true)->sole()->texte);
        $lut = $question->answers->firstWhere('texte', 'LUT');
        $this->assertStringContainsString(chr(64 + $lut->ordre).' : Le LUT transforme', $question->explication);
        $this->assertSame(11, Lesson::sole()->ordre);
        $this->assertNull(Lesson::sole()->url_video_pratique);
        $this->assertSame('https://elite.tfs237.com/cours_theorique/audiovisuel__module3__lecon11.html', Lesson::sole()->url_web);
        $this->seed(CoursJsonSeeder::class);
        $this->assertDatabaseCount('packs', 1);
        $this->assertDatabaseCount('modules', 2);
        $this->assertDatabaseCount('lessons', 1);
        $this->assertDatabaseCount('quizzes', 1);
        $this->assertSame($question->id, Quiz::sole()->questions()->sole()->id);
        $this->assertSame($answerIds, $question->fresh()->answers->modelKeys());
        Http::assertSentCount(1);
    }

    public function test_reordering_preserves_the_correct_text_in_every_position(): void
    {
        Http::fake(['*' => Http::response([
            'module' => ['titre' => 'Test'],
            'questions' => [[
                'question' => 'Quelle réponse ?',
                'options' => ['A' => 'Correcte', 'B' => 'Deux', 'C' => 'Trois', 'D' => 'Quatre'],
                'bonne_reponse' => 'A',
                'explication' => ['pourquoi_incorrectes' => ['B' => 'Deux est incorrecte.']],
            ]],
        ])]);
        $seeder = new class extends CoursJsonSeeder
        {
            public array $letters;

            protected function answerOrder(): array
            {
                return $this->letters;
            }
        };
        foreach ([['A', 'B', 'C', 'D'], ['D', 'A', 'B', 'C'], ['C', 'D', 'A', 'B'], ['B', 'C', 'D', 'A']] as $position => $letters) {
            // Each permutation is tested on a new quiz; production relaunches preserve it.
            Quiz::query()->delete();
            $seeder->letters = $letters;
            $seeder->run();
            $question = Quiz::sole()->questions()->sole();
            $this->assertSame('Correcte', $question->correctAnswers()->sole()->texte);
            $this->assertSame($position + 1, $question->correctAnswers()->sole()->ordre);
            $this->assertSame(4, $question->answers()->count());
            $this->assertSame(array_map(fn ($letter) => ['A' => 'Correcte', 'B' => 'Deux', 'C' => 'Trois', 'D' => 'Quatre'][$letter], $letters), $question->answers->pluck('texte')->all());
            $this->assertStringContainsString(chr(65 + array_search('B', $letters)).' : Deux est incorrecte.', $question->explication);
        }
    }

    public function test_links_only_import_preserves_quizzes_and_rejects_missing_files(): void
    {
        Http::fake(['*' => Http::response([
            'module' => ['titre' => 'Test'],
            'questions' => [[
                'question' => 'Question', 'bonne_reponse' => 'A',
                'options' => ['A' => 'Un', 'B' => 'Deux', 'C' => 'Trois', 'D' => 'Quatre'],
            ]],
        ])]);
        $this->seed(CoursJsonSeeder::class);
        $before = Quiz::with('questions.answers')->sole()->toArray();
        Lesson::sole()->update(['url_web' => null]);
        $this->seed(CoursTheoriqueLinksSeeder::class);
        $this->seed(CoursTheoriqueLinksSeeder::class);
        $this->assertSame('https://elite.tfs237.com/cours_theorique/audiovisuel__module3__lecon11.html', Lesson::sole()->url_web);
        $this->assertSame($before, Quiz::with('questions.answers')->sole()->toArray());
        Http::assertSentCount(1);

        Lesson::sole()->update(['url_web' => 'https://example.com/custom']);
        $this->seed(CoursTheoriqueLinksSeeder::class);
        $this->assertSame('https://example.com/custom', Lesson::sole()->url_web);

        File::delete(public_path('cours_theorique/audiovisuel__module3__lecon11.html'));
        Lesson::sole()->update(['url_web' => 'https://example.com/unchanged']);
        try {
            $this->seed(CoursTheoriqueLinksSeeder::class);
            $this->fail('Missing HTML must fail before updating links.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Cours HTML absent', $e->getMessage());
            $this->assertSame('https://example.com/unchanged', Lesson::sole()->url_web);
        }
    }

    public function test_existing_data_and_digital_are_preserved_even_when_source_changes(): void
    {
        $this->fakeSimpleQuiz();
        $this->seed(CoursJsonSeeder::class);
        $pack = Pack::sole();
        $pack->update(['prix_points' => 999, 'active' => false, 'description' => 'Configuration VPS']);
        Lesson::sole()->update(['titre' => 'Cours personnalisé', 'url_web' => 'https://example.com/custom', 'active' => false]);
        $quiz = Quiz::sole();
        $quiz->update(['ordre' => 7, 'titre' => 'Quiz personnalisé']);
        $quiz->questions()->create(['ordre' => 20, 'enonce' => 'Question supplémentaire', 'type' => 'qcm', 'points' => 5, 'active' => true]);
        $userId = DB::table('elite_users')->insertGetId([
            'nom' => 'Client', 'prenom' => 'Existant', 'telephone' => '690000001',
            'dernier_diplome' => 'secondaire', 'ville' => 'Douala', 'password' => 'fixture',
            'referral_code' => 'EXISTANT', 'solde_points' => 123,
        ]);
        DB::table('user_packs')->insert(['user_id' => $userId, 'pack_id' => $pack->id,
            'duree_choisie' => '6 mois', 'prix_paye' => 300, 'date_achat' => now()]);
        DB::table('lesson_progress')->insert(['user_id' => $userId, 'lesson_id' => Lesson::sole()->id,
            'completed' => true, 'temps_passe_secondes' => 700]);
        DB::table('quiz_results')->insert(['user_id' => $userId, 'quiz_id' => $quiz->id,
            'note' => 20, 'total_questions' => 1, 'bonnes_reponses' => 1, 'reussi' => true]);
        $digital = Pack::create([
            'category_id' => $pack->category_id, 'nom' => 'Digital', 'slug' => 'developpement-web-et-app',
            'description' => 'Digital existant', 'niveau_requis' => 'BAC', 'durees_disponibles' => ['6 mois'],
        ]);
        $digitalModule = Module::create(['pack_id' => $digital->id, 'nom' => 'Digital module', 'ordre' => 1, 'type' => 'theorique']);
        Lesson::create(['module_id' => $digitalModule->id, 'titre' => 'Digital existant', 'ordre' => 1, 'url_web' => 'https://example.com/digital']);
        $tables = ['categories', 'packs', 'modules', 'lessons', 'quizzes', 'quiz_questions', 'quiz_answers',
            'elite_users', 'user_packs', 'lesson_progress', 'quiz_results'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        File::put(storage_path('app/private/cours-quizzes/quiz-test.json'), json_encode([
            'module' => ['titre' => 'Nouveau titre source'], 'questions' => [[
                'question' => 'Source modifiée', 'bonne_reponse' => 'D',
                'options' => ['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D'],
            ]],
        ]));
        $this->seed(CoursJsonSeeder::class);
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
        Http::assertSentCount(1);
    }

    public function test_dry_run_rolls_back_and_real_import_can_follow(): void
    {
        $this->fakeSimpleQuiz();
        $seeder = new CoursJsonSeeder;
        $seeder->dryRun = true;
        $seeder->run();
        $this->assertSame(1, $seeder->report['created']['packs']);
        $this->assertDatabaseCount('packs', 0);
        $this->assertDatabaseCount('quiz_answers', 0);
        $seeder->dryRun = false;
        $seeder->run();
        $this->assertDatabaseCount('packs', 1);
        $this->assertDatabaseCount('quiz_answers', 4);
    }

    public function test_ambiguous_module_rolls_back_new_records(): void
    {
        $this->fakeSimpleQuiz();
        $this->seed(CoursJsonSeeder::class);
        Module::create(['pack_id' => Pack::sole()->id, 'ordre' => 4, 'nom' => 'Doublon', 'type' => 'theorique']);
        $catalog = json_decode(File::get(public_path('cours.json')), true);
        array_unshift($catalog['filieres'][0]['modules'], ['numero' => 2, 'nom' => 'Nouveau module', 'lecons' => []]);
        File::put(public_path('cours.json'), json_encode($catalog));
        try {
            $this->seed(CoursJsonSeeder::class);
            $this->fail('Ambiguous module must fail.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('ambiguë', $e->getMessage());
            $this->assertSame(0, Module::where('ordre', 2)->count());
            $this->assertDatabaseCount('modules', 3);
        }
    }

    private function fakeSimpleQuiz(): void
    {
        Http::fake(['*' => Http::response([
            'module' => ['titre' => 'Test'], 'questions' => [[
                'question' => 'Question', 'bonne_reponse' => 'A',
                'options' => ['A' => 'Un', 'B' => 'Deux', 'C' => 'Trois', 'D' => 'Quatre'],
            ]],
        ])]);
    }

    public function test_video_correction_is_targeted_reversible_and_idempotent(): void
    {
        $this->fakeSimpleQuiz();
        $this->seed(CoursJsonSeeder::class);
        $direct = 'https://drive.usercontent.google.com/download?id=video&export=download&confirm=t';
        $this->assertSame($direct, Lesson::sole()->url_video_explication);
        $lesson = Lesson::sole();
        $lesson->update(['url_video_explication' => 'https://drive.google.com/file/d/video/preview',
            'url_video' => 'https://example.com/custom.mp4']);
        $before = $lesson->fresh()->getAttributes();
        $quizBefore = Quiz::with('questions.answers')->sole()->toArray();
        $seeder = new CoursVideoLinksSeeder;
        $seeder->dryRun = true;
        $seeder->run();
        $this->assertSame(1, $seeder->report['urls_changed']);
        $this->assertSame($before, $lesson->fresh()->getAttributes());
        $seeder->dryRun = false;
        $seeder->run();
        $after = $before;
        $after['url_video_explication'] = $direct;
        $this->assertSame($after, $lesson->fresh()->getAttributes());
        $this->assertSame($quizBefore, Quiz::with('questions.answers')->sole()->toArray());
        $backup = json_decode(File::get($seeder->report['backup']), true);
        $this->assertSame(['url_video_explication' => $before['url_video_explication']], $backup['changes'][0]['before']);
        $seeder->run();
        $this->assertSame(0, $seeder->report['urls_changed']);
        $this->assertArrayNotHasKey('backup', $seeder->report);
        $this->assertSame($after, $lesson->fresh()->getAttributes());
        $lesson->update(['url_video_explication' => 'https://drive.google.com/file/d/custom-file/view']);
        $seeder->run();
        $this->assertSame(0, $seeder->report['urls_changed']);
        $this->assertSame(1, $seeder->report['custom_or_empty_preserved']);
    }

    public function test_video_correction_rejects_ambiguous_lessons_without_modification(): void
    {
        $this->fakeSimpleQuiz();
        $this->seed(CoursJsonSeeder::class);
        $lesson = Lesson::sole();
        $lesson->update(['url_video_explication' => 'https://drive.google.com/file/d/video/view']);
        $lesson->replicate()->save();
        $before = DB::table('lessons')->orderBy('id')->get()->toJson();
        $this->artisan('cours:correct-videos', ['--force' => true])->assertFailed();
        $this->assertSame($before, DB::table('lessons')->orderBy('id')->get()->toJson());
    }

    public function test_direct_video_url_keeps_file_identity_and_hosted_mp4_urls(): void
    {
        $resolver = new DirectVideoUrl;
        $this->assertNull($resolver->fromFile(null));
        $this->assertSame('https://cdn.example.com/movie.mp4', $resolver->fromFile(['url_direct' => 'https://cdn.example.com/movie.mp4']));
        $this->assertNull($resolver->driveId('https://drive.google.com.evil.example/file/d/video/view'));
        $this->assertSame('https://drive.usercontent.google.com/download?id=video&export=download&confirm=t&resourcekey=key',
            $resolver->fromFile(['id' => 'video', 'url_direct' => 'https://drive.google.com/uc?id=video&export=download&resourcekey=key']));
        $this->expectException(\RuntimeException::class);
        $resolver->fromFile(['id' => 'different', 'url' => 'https://drive.google.com/file/d/video/view']);
    }

    public function test_full_deployment_bundle_imports_offline_and_second_run_changes_nothing(): void
    {
        $this->app->usePublicPath(base_path('public'));
        $seeder = new CoursJsonSeeder;
        $seeder->offline = true;
        $seeder->run();
        $this->assertDatabaseCount('packs', 14);
        $this->assertDatabaseCount('modules', 82);
        $this->assertDatabaseCount('lessons', 420);
        $this->assertDatabaseCount('quizzes', 81);
        $this->assertSame(359, Lesson::where('url_web', 'like', 'https://elite.tfs237.com/cours_theorique/%')->count());
        $tables = ['categories', 'packs', 'modules', 'lessons', 'quizzes', 'quiz_questions', 'quiz_answers'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $seeder->run();
        $this->assertSame([], $seeder->report['created']);
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
        Http::assertNothingSent();
    }

    public function test_production_command_simulates_and_imports_offline(): void
    {
        File::ensureDirectoryExists(storage_path('app/private/cours-quizzes'));
        File::put(storage_path('app/private/cours-quizzes/quiz-test.json'), json_encode([
            'module' => ['titre' => 'Test'], 'questions' => [[
                'question' => 'Question', 'bonne_reponse' => 'A',
                'options' => ['A' => 'Un', 'B' => 'Deux', 'C' => 'Trois', 'D' => 'Quatre'],
            ]],
        ]));
        $this->artisan('cours:import', ['--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('packs', 0);
        $this->artisan('cours:import', ['--force' => true])->assertSuccessful();
        $this->assertDatabaseCount('packs', 1);
        Http::assertNothingSent();
    }

    public function test_offline_command_reports_missing_quiz_without_any_insert(): void
    {
        $this->artisan('cours:import', ['--force' => true])->assertFailed();
        $this->assertDatabaseCount('packs', 0);
        Http::assertNothingSent();
    }

    public function test_invalid_remote_quiz_leaves_database_untouched(): void
    {
        Http::fake(['*' => Http::response('<html>Connexion Google requise</html>')]);
        try {
            $this->seed(CoursJsonSeeder::class);
            $this->fail('Invalid JSON must fail the import.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('quiz-test', $e->getMessage());
            $this->assertSame(0, Pack::count());
        }
    }

    public function test_joins_separate_corrections_by_id_and_accepts_option_lists(): void
    {
        Http::fake(['*' => Http::response([
            'titre' => 'Gestion de projet', 'module' => 1,
            'consignes' => ['Lisez les questions.', 'Choisissez une réponse.'],
            'questions' => [
                ['id' => 'q1', 'question' => 'Première question', 'options' => [
                    ['id' => 'D', 'texte' => 'Quatre'], ['id' => 'B', 'texte' => 'Deux'],
                    ['id' => 'A', 'texte' => 'Un'], ['id' => 'C', 'texte' => 'Trois'],
                ]],
                ['id' => 'q2', 'question' => 'Seconde question', 'options' => [
                    'A' => 'Un', 'B' => 'Deux', 'C' => 'Trois', 'D' => 'Quatre',
                ]],
            ],
            'Correction et Explications' => [
                ['question_id' => 'q2', 'bonne_reponse' => 'C', 'explication' => 'Correction deux'],
                ['question_id' => 'q1', 'bonne_reponse' => 'B', 'explication' => 'Correction un'],
            ],
        ])]);
        $this->seed(CoursJsonSeeder::class);
        $questions = Quiz::sole()->questions;
        $this->assertSame("Lisez les questions.\nChoisissez une réponse.", Quiz::sole()->description);
        $this->assertSame('Correction un', $questions[0]->explication);
        $this->assertSame('Deux', $questions[0]->correctAnswers()->sole()->texte);
        $this->assertSame('Trois', $questions[1]->correctAnswers()->sole()->texte);
    }

    public function test_accepts_inline_correction_objects_and_strings(): void
    {
        Http::fake(['*' => Http::response([
            'titre_quiz' => 'Secrétariat', 'module' => 'Module 4',
            'questions' => [
                ['id' => 1, 'question' => 'Question un',
                    'options' => ['A' => 'Un', 'B' => 'Deux', 'C' => 'Trois', 'D' => 'Quatre'],
                    'correction_et_explications' => ['reponse_correcte' => 'A', 'explication' => 'Explication un']],
                ['id' => 2, 'question' => 'Question deux',
                    'options' => ['A' => 'Un', 'B' => 'Deux', 'C' => 'Trois', 'D' => 'Quatre'],
                    'reponse_correcte' => 'D', 'correction_et_explications' => 'Explication deux'],
            ],
        ])]);
        $this->seed(CoursJsonSeeder::class);
        $questions = Quiz::sole()->questions;
        $this->assertSame('Quiz - Secrétariat', Quiz::sole()->titre);
        $this->assertSame('Un', $questions[0]->correctAnswers()->sole()->texte);
        $this->assertSame('Explication deux', $questions[1]->explication);
        $this->assertSame('Quatre', $questions[1]->correctAnswers()->sole()->texte);
    }
}
