<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CourseSchedule;
use App\Models\EliteUser;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\ModuleUnlock;
use App\Models\Pack;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\UserPack;
use App\Models\PartnerPaymentPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseService
{
    public function __construct(private readonly CourseVideoUrl $videoUrl)
    {
    }

    public function getCategories(): array
    {
        $categories = Category::active()
            ->with(['packs' => function ($query) {
                $query->active()->orderBy('ordre');
            }])
            ->orderBy('ordre')
            ->get();

        return $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'nom' => $category->nom,
                'slug' => $category->slug,
                'description' => $category->description,
                'image_url' => $category->image_url,
                'couleur' => $category->couleur,
                'packs_count' => $category->packs->count(),
                'packs' => $category->packs->map(function ($pack) {
                    return [
                        'id' => $pack->id,
                        'nom' => $pack->nom,
                        'slug' => $pack->slug,
                        'prix_points' => $pack->prix_points,
                        'prix_fcfa' => $pack->prix_fcfa_effectif,
                        'niveau_requis' => $pack->niveau_requis,
                    ];
                }),
            ];
        })->toArray();
    }

    public function getAllPacks(array $filters = []): array
    {
        $query = Pack::active()->with('category');

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['niveau_requis'])) {
            $query->where('niveau_requis', $filters['niveau_requis']);
        }

        $packs = $query->orderBy('ordre')->get();

        return $packs->map(function ($pack) {
            return [
                'id' => $pack->id,
                'nom' => $pack->nom,
                'slug' => $pack->slug,
                'description' => $pack->description,
                'image_url' => $pack->image_url,
                'category' => [
                    'id' => $pack->category->id,
                    'nom' => $pack->category->nom,
                ],
                'niveau_requis' => $pack->niveau_requis,
                'durees_disponibles' => $pack->durees_disponibles,
                'diplomes_possibles' => $pack->diplomes_possibles,
                'prix_points' => $pack->prix_points,
                'prix_fcfa' => $pack->prix_fcfa_effectif,
                'debouches' => $pack->debouches,
            ];
        })->toArray();
    }

    public function getRecommendedPacks(EliteUser $user): array
    {
        if (!$user->profile_chosen) {
            return [];
        }

        $profileId = $user->profileChoice->profile_id ?? null;
        
        if (!$profileId) {
            return [];
        }
        
        $packs = Pack::active()
            ->whereHas('profiles', function ($query) use ($profileId) {
                $query->where('career_profiles.id', $profileId);
            })
            ->with('category')
            ->orderByRaw(
                '(SELECT priorite FROM pack_profiles 
                  WHERE pack_profiles.pack_id = packs.id 
                  AND pack_profiles.profile_id = ?) DESC', 
                [$profileId]
            )
            ->get();

        return $packs->map(function ($pack) {
            return [
                'id' => $pack->id,
                'nom' => $pack->nom,
                'slug' => $pack->slug,
                'description' => $pack->description,
                'image_url' => $pack->image_url,
                'category' => [
                    'id' => $pack->category->id,
                    'nom' => $pack->category->nom,
                ],
                'niveau_requis' => $pack->niveau_requis,
                'prix_points' => $pack->prix_points,
                'recommended' => true,
            ];
        })->toArray();
    }

    public function getPackDetails(int $packId): array
    {
        $pack = Pack::with([
            'category',
            'modules' => function ($query) {
                $query->active()->orderBy('ordre')->with([
                    'lessons' => function ($q) {
                        $q->active()->orderBy('ordre');
                    },
                    'quizzes' => function ($q) {
                        $q->active()->orderBy('ordre');
                    }
                ]);
            }
        ])->findOrFail($packId);

        return [
            'id' => $pack->id,
            'nom' => $pack->nom,
            'slug' => $pack->slug,
            'description' => $pack->description,
            'image_url' => $pack->image_url,
            'category' => [
                'id' => $pack->category->id,
                'nom' => $pack->category->nom,
            ],
            'niveau_requis' => $pack->niveau_requis,
            'durees_disponibles' => $pack->durees_disponibles,
            'diplomes_possibles' => $pack->diplomes_possibles,
            'prix_points' => $pack->prix_points,
            'prix_fcfa' => $pack->prix_fcfa_effectif,
            'debouches' => $pack->debouches,
            'total_modules' => $pack->modules->count(),
            'total_lessons' => $pack->modules->sum(fn($m) => $m->lessons->count()),
            'modules' => $pack->modules->map(function ($module) {
                return [
                    'id' => $module->id,
                    'nom' => $module->nom,
                    'description' => $module->description,
                    'type' => $module->type,
                    'lessons_count' => $module->lessons->count(),
                    'has_quiz' => $module->quizzes->isNotEmpty(),
                ];
            }),
        ];
    }

    public function getPackModules(EliteUser $user, int $packId): array
    {
        $hasPack = $this->userHasPack($user, $packId);

        $pack = Pack::with([
            'modules' => function ($query) {
                $query->active()->orderBy('ordre')->orderBy('id')->with([
                    'lessons' => function ($q) {
                        $q->active()->orderBy('ordre')->orderBy('id');
                    },
                    'quizzes' => function ($q) {
                        $q->active()->orderBy('ordre');
                    }
                ]);
            }
        ])->findOrFail($packId);

        $modules = $pack->modules;
        $modulesData = [];

        foreach ($modules as $moduleIndex => $module) {
            $isFirstModule = $moduleIndex === 0;
            $isModuleUnlocked = false;

            if ($hasPack) {
                if ($isFirstModule) {
                    $isModuleUnlocked = true;
                } else {
                    // Le module est déverrouillé si le module précédent est validé ou si explicitement débloqué
                    $prevModule = $modules[$moduleIndex - 1];
                    $prevCompleted = $prevModule->isCompletedBy($user);
                    $isModuleUnlocked = $prevCompleted || $module->isUnlockedFor($user);
                }
            }

            $allLessons = $module->lessons;
            $totalLessons = $allLessons->count();
            $completedLessonsCount = 0;

            if ($hasPack && $isModuleUnlocked && $totalLessons > 0) {
                $completedLessonsCount = LessonProgress::where('user_id', $user->id)
                    ->whereIn('lesson_id', $allLessons->pluck('id'))
                    ->where('completed', true)
                    ->count();
            }

            $allLessonsDone = ($totalLessons > 0) ? ($completedLessonsCount >= $totalLessons) : true;
            $activeQuiz = $module->quizzes->first();
            $hasQuiz = !is_null($activeQuiz);

            $quizResult = null;
            if ($hasPack && $hasQuiz) {
                $quizResult = QuizResult::where('user_id', $user->id)
                    ->whereIn('quiz_id', $module->quizzes->pluck('id'))
                    ->orderByDesc('note')
                    ->first();
            }

            $quizPassed = $quizResult ? (bool) $quizResult->reussi : false;

            // Règle : Si aucun quiz n'est dispo pour un module, on valide le module entier comme terminé dès que la dernière leçon est terminée
            $isModuleCompleted = false;
            if ($hasPack && $isModuleUnlocked) {
                if (!$hasQuiz) {
                    $isModuleCompleted = $allLessonsDone && $totalLessons > 0;
                } else {
                    $isModuleCompleted = $allLessonsDone && $quizPassed;
                }
            }

            $modulesData[] = [
                'id' => $module->id,
                'nom' => $module->nom,
                'description' => $module->description,
                'type' => $module->type,
                'ordre' => $module->ordre,
                'note_passage' => $module->note_passage ?? 14,
                'note_parrainage' => $module->note_parrainage ?? 10,
                'parrainages_requis' => $module->parrainages_requis ?? 4,
                'lessons_count' => $totalLessons,
                'completed_count' => $completedLessonsCount,
                'has_quiz' => $hasQuiz,
                'quiz_passed' => $quizPassed,
                'is_unlocked' => $isModuleUnlocked,
                'is_completed' => $isModuleCompleted,
                'has_access' => $hasPack,
                'quiz_info' => $hasQuiz && $quizResult ? [
                    'id' => $activeQuiz->id,
                    'titre' => $activeQuiz->titre,
                    'best_score' => $quizResult->note,
                    'passed' => $quizPassed,
                    'attempts' => QuizResult::where('user_id', $user->id)->where('quiz_id', $activeQuiz->id)->count(),
                ] : null,
            ];
        }

        return $modulesData;
    }

    public function getModuleLessons(EliteUser $user, int $moduleId): array
    {
        $module = Module::with(['pack', 'lessons' => function ($q) {
            $q->active()->orderBy('ordre')->orderBy('id');
        }, 'quizzes' => function ($q) {
            $q->active()->orderBy('ordre');
        }])->findOrFail($moduleId);

        $hasPack = $this->userHasPack($user, $module->pack_id);

        if ($hasPack) {
            $this->verifyModuleAccess($user, $module);
        }

        $allLessons = $module->lessons;
        $completedLessonIds = $hasPack
            ? LessonProgress::where('user_id', $user->id)
                ->whereIn('lesson_id', $allLessons->pluck('id'))
                ->where('completed', true)
                ->pluck('lesson_id')
                ->toArray()
            : [];

        $progresses = $hasPack
            ? LessonProgress::where('user_id', $user->id)
                ->whereIn('lesson_id', $allLessons->pluck('id'))
                ->get()
                ->keyBy('lesson_id')
            : collect();

        $isModuleUnlocked = $hasPack && $this->isModuleAccessible($user, $module);

        $lessonsProgress = $allLessons->map(function ($lesson, $index) use ($user, $hasPack, $allLessons, $completedLessonIds, $progresses, $isModuleUnlocked) {
            $progress = $progresses->get($lesson->id);
            $isCompleted = in_array($lesson->id, $completedLessonIds);
            $isUnlocked = false;

            if ($hasPack && $isModuleUnlocked) {
                if ($index === 0) {
                    $isUnlocked = true;
                } else {
                    $prevLesson = $allLessons[$index - 1];
                    $isUnlocked = in_array($prevLesson->id, $completedLessonIds);
                }
            }

            return [
                'id' => $lesson->id,
                'module_id' => $lesson->module_id,
                'titre' => $lesson->titre,
                'duree_minutes' => $lesson->duree_minutes,
                'is_completed' => $isCompleted,
                'temps_passe' => $progress ? $progress->temps_passe_secondes : 0,
                'is_unlocked' => $isUnlocked,
                'has_access' => $hasPack,
                'has_video' => !empty($lesson->url_video) || !empty($lesson->url_video_explication) || !empty($lesson->url_video_pratique),
                'has_web_link' => !empty($lesson->url_web),
                'url_video' => $hasPack && $isUnlocked ? $this->videoUrl->playback($lesson, 'explication', $lesson->url_video) : null,
                'url_video_explication' => $hasPack && $isUnlocked ? $this->videoUrl->playback($lesson, 'explication', $lesson->url_video_explication ?: $lesson->url_video) : null,
                'url_video_pratique' => $hasPack && $isUnlocked ? $this->videoUrl->playback($lesson, 'pratique', $lesson->url_video_pratique ?: $lesson->url_video) : null,
                'url_web' => $hasPack && $isUnlocked ? $lesson->url_web : null,
            ];
        })->toArray();

        $activeQuiz = $module->quizzes->first();
        $hasQuiz = !is_null($activeQuiz);
        $quizResult = null;

        if ($hasPack && $hasQuiz) {
            $quizResult = QuizResult::where('user_id', $user->id)
                ->whereIn('quiz_id', $module->quizzes->pluck('id'))
                ->orderByDesc('note')
                ->first();
        }

        $allLessonsDone = count($completedLessonIds) >= $allLessons->count() && $allLessons->count() > 0;
        $quizPassed = $quizResult ? (bool) $quizResult->reussi : false;

        $moduleCompleted = false;
        if ($hasPack && $isModuleUnlocked) {
            if (!$hasQuiz) {
                $moduleCompleted = $allLessonsDone;
            } else {
                $moduleCompleted = $allLessonsDone && $quizPassed;
            }
        }

        return [
            'module' => [
                'id' => $module->id,
                'nom' => $module->nom,
                'description' => $module->description,
                'type' => $module->type,
                'pack_id' => $module->pack_id,
                'pack_nom' => $module->pack?->nom,
                'is_unlocked' => $isModuleUnlocked,
                'is_completed' => $moduleCompleted,
                'completed_count' => count($completedLessonIds),
                'lessons_count' => $allLessons->count(),
                'has_access' => $hasPack,
                'note_passage' => $module->note_passage ?? 14,
                'note_parrainage' => $module->note_parrainage ?? 10,
                'parrainages_requis' => $module->parrainages_requis ?? 4,
            ],
            'lessons' => $lessonsProgress,
            'has_quiz' => $hasQuiz,
            'quiz' => $hasQuiz ? [
                'id' => $activeQuiz->id,
                'titre' => $activeQuiz->titre,
                'description' => $activeQuiz->description,
                'duree_minutes' => $activeQuiz->duree_minutes,
                'is_unlocked' => $allLessonsDone,
                'best_score' => $quizResult ? $quizResult->note : null,
                'passed' => $quizPassed,
                'attempts' => $quizResult ? QuizResult::where('user_id', $user->id)->where('quiz_id', $activeQuiz->id)->count() : 0,
            ] : null,
        ];
    }

    public function getLesson(EliteUser $user, int $lessonId): array
    {
        $lesson = Lesson::with(['module.pack'])->findOrFail($lessonId);

        // Fallback si module_id n'est pas encore défini mais chapter_id l'est
        if (!$lesson->module_id && $lesson->chapter_id) {
            $chapter = DB::table('chapters')->where('id', $lesson->chapter_id)->first();
            if ($chapter && $chapter->module_id) {
                $lesson->module_id = $chapter->module_id;
                $lesson->save();
                $lesson->load('module.pack');
            }
        }

        $packId = $lesson->module?->pack_id;
        $this->verifyPackAccess($user, $packId);
        $this->verifyModuleAccess($user, $lesson->module);
        $this->verifyLessonAccess($user, $lesson);
        $this->verifyCourseSchedule($user, $lesson);

        $progress = LessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lessonId)
            ->first();

        // Récupérer toutes les leçons actives du module ordonnées
        $allLessons = Lesson::where('module_id', $lesson->module_id)
            ->active()
            ->orderBy('ordre')
            ->orderBy('id')
            ->get();

        $currentIndex = $allLessons->search(fn($l) => $l->id === $lesson->id);
        $previousLesson = ($currentIndex !== false && $currentIndex > 0) ? $allLessons[$currentIndex - 1] : null;
        $nextLesson = ($currentIndex !== false && $currentIndex < $allLessons->count() - 1) ? $allLessons[$currentIndex + 1] : null;

        return [
            'id' => $lesson->id,
            'titre' => $lesson->titre,
            'contenu_texte' => $lesson->contenu_texte,
            'url_web' => $lesson->url_web,
            'url_video' => $this->videoUrl->playback($lesson, 'explication', $lesson->url_video),
            'url_video_explication' => $this->videoUrl->playback($lesson, 'explication', $lesson->url_video_explication ?: $lesson->url_video),
            'url_video_pratique' => $this->videoUrl->playback($lesson, 'pratique', $lesson->url_video_pratique ?: $lesson->url_video),
            'duree_minutes' => $lesson->duree_minutes,
            'is_completed' => $progress ? (bool)$progress->completed : false,
            'temps_passe' => $progress ? $progress->temps_passe_secondes : 0,
            'module' => $lesson->module ? [
                'id' => $lesson->module->id,
                'nom' => $lesson->module->nom,
                'pack_id' => $lesson->module->pack_id,
            ] : null,
            // Maintenir compatibilité avec chapter
            'chapter' => $lesson->module ? [
                'id' => $lesson->module->id,
                'nom' => $lesson->module->nom,
                'module' => [
                    'id' => $lesson->module->id,
                    'nom' => $lesson->module->nom,
                    'pack_id' => $lesson->module->pack_id,
                ]
            ] : null,
            'previous_lesson' => $previousLesson ? [
                'id' => $previousLesson->id,
                'titre' => $previousLesson->titre,
            ] : null,
            'next_lesson' => $nextLesson ? [
                'id' => $nextLesson->id,
                'titre' => $nextLesson->titre,
            ] : null,
        ];
    }

    public function markLessonComplete(EliteUser $user, int $lessonId, int $tempsPasse = 0): array
    {
        $lesson = Lesson::with('module.pack')->findOrFail($lessonId);
        $module = $lesson->module;

        $this->verifyPackAccess($user, $module->pack_id);
        $this->verifyModuleAccess($user, $module);
        $this->verifyLessonAccess($user, $lesson);

        $progress = LessonProgress::firstOrNew([
            'user_id' => $user->id,
            'lesson_id' => $lessonId,
        ]);

        $progress->completed = true;
        $progress->temps_passe_secondes = (int) ($progress->temps_passe_secondes ?? 0) + max(0, (int) $tempsPasse);
        $progress->date_completion = now();
        $progress->save();

        $userPack = UserPack::where('user_id', $user->id)
            ->where('pack_id', $module->pack_id)
            ->first();

        if ($userPack) {
            $userPack->updateProgression();
        }

        // Vérifier si toutes les leçons du module sont terminées
        $allLessonsDone = $module->allLessonsCompletedBy($user);
        $activeQuiz = $module->activeQuiz();
        $moduleCompleted = false;
        $nextModuleUnlocked = false;

        // RÈGLE : "si aucun quiz nest dispo pour un module on valide le module entier comme terminer des que la derniere lecon est terminer"
        if ($allLessonsDone && !$activeQuiz) {
            $moduleCompleted = true;
            $nextModuleUnlocked = $this->unlockNextModule($user, $module, 'auto');
        }

        return [
            'success' => true,
            'all_lessons_completed' => $allLessonsDone,
            'has_quiz' => !is_null($activeQuiz),
            'module_completed' => $moduleCompleted,
            'next_module_unlocked' => $nextModuleUnlocked,
        ];
    }

    public function ensureModuleQuiz(Module $module): Quiz
    {
        $quiz = Quiz::where('module_id', $module->id)
            ->active()
            ->with(['questions.answers'])
            ->first();

        if (!$quiz) {
            $quiz = Quiz::create([
                'module_id' => $module->id,
                'titre' => 'Quiz de validation - ' . $module->nom,
                'description' => 'Évaluez votre bonne compréhension du module : ' . $module->nom . '.',
                'note_totale' => 20,
                'duree_minutes' => 10,
                'ordre' => 1,
                'active' => true,
            ]);
        }

        if ($quiz->questions()->count() === 0) {
            $defaultQuestions = [
                [
                    'enonce' => "Avez-vous bien compris et assimilé les concepts clés présentés dans le module « " . $module->nom . " » ?",
                    'type' => 'qcm',
                    'points' => 2,
                    'ordre' => 1,
                    'explication' => "La bonne assimilation des notions clés est essentielle pour progresser dans la formation.",
                    'answers' => [
                        ['texte' => "Oui, j'ai bien compris l'ensemble des notions abordées", 'est_correcte' => true, 'ordre' => 1],
                        ['texte' => "Non, je dois encore revoir certains points", 'est_correcte' => false, 'ordre' => 2],
                    ],
                ],
                [
                    'enonce' => "Avez-vous suivi toutes les étapes (Théorie, Explication vidéo, Pratique) des leçons de ce module ?",
                    'type' => 'qcm',
                    'points' => 2,
                    'ordre' => 2,
                    'explication' => "La démarche pédagogique en 3 étapes garantit une maîtrise théorique et pratique.",
                    'answers' => [
                        ['texte' => "Oui, j'ai suivi avec attention toutes les étapes", 'est_correcte' => true, 'ordre' => 1],
                        ['texte' => "Non, j'ai sauté certaines étapes", 'est_correcte' => false, 'ordre' => 2],
                    ],
                ],
                [
                    'enonce' => "Vous sentez-vous prêt(e) à appliquer ces connaissances et à débloquer le module suivant ?",
                    'type' => 'qcm',
                    'points' => 2,
                    'ordre' => 3,
                    'explication' => "La confiance et la pratique permettent d'aborder sereinement la suite de votre parcours.",
                    'answers' => [
                        ['texte' => "Oui, je suis prêt(e) à continuer", 'est_correcte' => true, 'ordre' => 1],
                        ['texte' => "Non, je souhaite d'abord réviser", 'est_correcte' => false, 'ordre' => 2],
                    ],
                ],
            ];

            foreach ($defaultQuestions as $qData) {
                $answers = $qData['answers'];
                unset($qData['answers']);
                $question = $quiz->questions()->create($qData);
                foreach ($answers as $aData) {
                    $question->answers()->create($aData);
                }
            }
        }

        return $quiz->load(['questions.answers']);
    }

    public function getModuleQuiz(EliteUser $user, int $moduleId): array
    {
        $module = Module::with(['pack', 'quizzes.questions.answers'])->findOrFail($moduleId);
        $this->verifyPackAccess($user, $module->pack_id);
        $this->verifyModuleAccess($user, $module);
        $this->verifyAllModuleLessonsCompleted($user, $module);

        $quiz = $module->activeQuiz();
        if (!$quiz) {
            throw ValidationException::withMessages([
                'quiz' => ['Aucun quiz n\'est configuré pour ce module. Le module est validé dès la fin des leçons.']
            ]);
        }

        $attempts = QuizResult::where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->count();

        return [
            'id' => $quiz->id,
            'module_id' => $module->id,
            'titre' => $quiz->titre,
            'description' => $quiz->description,
            'duree_minutes' => $quiz->duree_minutes,
            'note_totale' => $quiz->note_totale ?: 20,
            'note_passage' => $module->note_passage ?: 14,
            'note_parrainage' => $module->note_parrainage ?: 10,
            'attempts' => $attempts,
            'questions' => $quiz->questions->map(function ($question) {
                return [
                    'id' => $question->id,
                    'enonce' => $question->enonce,
                    'image_url' => $question->image_url,
                    'type' => $question->type,
                    'points' => $question->points,
                    'answers' => $question->answers->map(function ($answer) {
                        return [
                            'id' => $answer->id,
                            'texte' => $answer->texte,
                            'est_correcte' => (bool)$answer->est_correcte,
                        ];
                    }),
                ];
            }),
        ];
    }

    public function submitQuiz(EliteUser $user, int $quizId, array $responses): array
    {
        $quiz = Quiz::with(['module.pack', 'questions.answers'])->findOrFail($quizId);
        $module = $quiz->module;

        $this->verifyPackAccess($user, $module->pack_id);
        $this->verifyAllModuleLessonsCompleted($user, $module);

        $questions = $quiz->questions;
        if ($questions->isEmpty()) {
            $quiz = $this->ensureModuleQuiz($module);
            $questions = $quiz->questions;
        }

        $totalPoints = 0;
        $earnedPoints = 0;
        $correctCount = 0;
        $userResponses = [];

        foreach ($questions as $question) {
            $points = $question->points > 0 ? $question->points : 2;
            $totalPoints += $points;
            $userAnswer = collect($responses)->firstWhere('question_id', $question->id);
            
            if ($userAnswer) {
                $correctAnswerIds = $question->answers()->where('est_correcte', true)->pluck('id')->toArray();
                $isCorrect = in_array($userAnswer['answer_id'], $correctAnswerIds);
                
                if ($isCorrect) {
                    $earnedPoints += $points;
                    $correctCount++;
                }

                $userResponses[] = [
                    'question_id' => $question->id,
                    'answer_id' => $userAnswer['answer_id'],
                    'is_correct' => $isCorrect,
                    'correct' => $isCorrect,
                    'explication' => $question->explication,
                ];
            }
        }

        $note = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 20 : 0;
        $note = round($note, 2);

        $passingGrade = $module->note_passage > 0 ? $module->note_passage : 14;
        $reussi = $questions->count() >= 10
            ? $correctCount >= 7
            : $note >= $passingGrade;

        $actionRequise = 'aucune';
        if ($reussi) {
            $actionRequise = 'aucune';
        } elseif ($note >= ($module->note_parrainage ?: 10)) {
            $actionRequise = 'parrainage';
        } else {
            $actionRequise = 'recommencer';
        }

        $tentative = QuizResult::where('user_id', $user->id)
            ->where('quiz_id', $quizId)
            ->count() + 1;

        $pointsParBonneReponse = max(0, (int) \App\Models\SystemSetting::get('points_par_bonne_reponse_quiz', 500));
        
        // 500 points par bonne réponse => 10/10 rapporte 5 000 points MAX
        $pointsGagnes = min(5000, $correctCount * $pointsParBonneReponse);

        $jackpot = \App\Models\SystemSetting::get('quiz_cagnotte_fcfa', [0, 1000, 5000, 10000, 25000, 50000, 100000, 250000, 500000, 750000, 1000000]);
        $palierAtteint = min($correctCount, 10);
        $gainCagnotte = (float) ($jackpot[$palierAtteint] ?? 0);

        $result = QuizResult::create([
            'user_id' => $user->id,
            'quiz_id' => $quizId,
            'note' => $note,
            'total_questions' => $questions->count(),
            'bonnes_reponses' => $correctCount,
            'points_gagnes' => $pointsGagnes,
            'palier_atteint' => $palierAtteint,
            'gain_cagnotte_fcfa' => $gainCagnotte,
            'reussi' => $reussi,
            'reponses_utilisateur' => $userResponses,
            'tentative' => $tentative,
            'action_requise' => $actionRequise,
        ]);

        if ($pointsGagnes > 0) {
            $user->addPoints($pointsGagnes);
        }

        if ($reussi) {
            $this->unlockNextModule($user, $module, 'score');
        }

        $parrainageRequis = $actionRequise === 'parrainage';
        $nombreParrainagesRequis = $parrainageRequis ? ($module->parrainages_requis ?? 4) : 0;
        $filleulsActuels = $user->referralHistory()->count();

        return [
            'note' => $note,
            'note_sur_20' => $note,
            'total_questions' => $questions->count(),
            'bonnes_reponses' => $correctCount,
            'points_gagnes' => $pointsGagnes,
            'bonus_premier_essai_100_points' => false,
            'palier_atteint' => $palierAtteint,
            'gain_cagnotte_fcfa' => $gainCagnotte,
            'reussi' => $reussi,
            'module_suivant_debloque' => $reussi,
            'chapitre_suivant_debloque' => $reussi, // Alias
            'action_requise' => $actionRequise,
            'parrainages_requis' => $nombreParrainagesRequis,
            'options' => [
                'peut_recommencer' => true,
                'parrainage_requis' => $parrainageRequis,
                'nombre_parrainages_requis' => $module->parrainages_requis ?? 4,
                'filleuls_actuels' => $filleulsActuels,
                'parrainages_manquants' => max(0, ($module->parrainages_requis ?? 4) - $filleulsActuels),
            ],
            'corrections' => $userResponses,
            'message' => $this->getResultMessage($note, $reussi, $actionRequise, $module),
        ];
    }

    public function unlockNextModule(EliteUser $user, Module $currentModule, string $method): bool
    {
        $nextModule = Module::where('pack_id', $currentModule->pack_id)
            ->where(function($q) use ($currentModule) {
                $q->where('ordre', '>', $currentModule->ordre)
                  ->orWhere(function($q2) use ($currentModule) {
                      $q2->where('ordre', $currentModule->ordre)
                         ->where('id', '>', $currentModule->id);
                  });
            })
            ->active()
            ->orderBy('ordre')
            ->orderBy('id')
            ->first();

        if ($nextModule) {
            ModuleUnlock::firstOrCreate([
                'user_id' => $user->id,
                'module_id' => $nextModule->id,
            ], [
                'unlock_method' => $method,
            ]);
            return true;
        }

        return false;
    }

    public function unlockModuleByReferral(EliteUser $user, int $moduleId): array
    {
        $module = Module::with('pack')->findOrFail($moduleId);
        $this->verifyPackAccess($user, $module->pack_id);

        $lastResult = QuizResult::where('user_id', $user->id)
            ->whereHas('quiz', function ($q) use ($moduleId) {
                $q->where('module_id', $moduleId);
            })
            ->where('action_requise', 'parrainage')
            ->latest()
            ->first();

        if (!$lastResult) {
            throw ValidationException::withMessages([
                'quiz' => ['Vous devez d\'abord passer le quiz avec une note admissible pour le parrainage.']
            ]);
        }

        $parrainagesDepuisQuiz = $user->referralHistory()
            ->where('created_at', '>=', $lastResult->created_at)
            ->count();

        $parrainagesRequis = $module->parrainages_requis ?? 4;
        $parrainagesEffectues = $lastResult->parrainages_effectues + $parrainagesDepuisQuiz;

        if ($parrainagesEffectues < $parrainagesRequis) {
            return [
                'unlocked' => false,
                'parrainages_effectues' => $parrainagesEffectues,
                'parrainages_requis' => $parrainagesRequis,
                'parrainages_restants' => $parrainagesRequis - $parrainagesEffectues,
                'message' => "Il vous manque encore " . ($parrainagesRequis - $parrainagesEffectues) . " parrainage(s) pour débloquer le module suivant.",
            ];
        }

        $this->unlockNextModule($user, $module, 'parrainage');

        $lastResult->update([
            'parrainages_effectues' => $parrainagesEffectues,
            'reussi' => true,
            'action_requise' => 'aucune',
        ]);

        return [
            'unlocked' => true,
            'message' => 'Félicitations ! Le module suivant a été débloqué grâce à vos parrainages.',
        ];
    }

    private function userHasPack(EliteUser $user, int $packId): bool
    {
        $hasPack = UserPack::where('user_id', $user->id)
            ->where('pack_id', $packId)
            ->where('statut', '!=', 'expire')
            ->exists();

        if ($hasPack) {
            return true;
        }

        // Si l'étudiant est rattaché à un partenaire
        if ($user->partner_id) {
            $plan = PartnerPaymentPlan::where('partner_id', $user->partner_id)
                ->where('pack_id', $packId)
                ->where('active', true)
                ->first();

            if (!$plan) {
                $pack = Pack::find($packId);
                if ($pack) {
                    app(PartnerPaymentService::class)->attachPlanToPack($user, $pack);
                    return true;
                }
            }
        }

        return false;
    }

    private function verifyPackAccess(EliteUser $user, ?int $packId): void
    {
        if (!$packId || !$this->userHasPack($user, $packId)) {
            throw ValidationException::withMessages([
                'pack' => ['Vous n\'avez pas accès à ce pack. Veuillez l\'acheter d\'abord.']
            ]);
        }
    }

    private function isModuleAccessible(EliteUser $user, Module $module): bool
    {
        $firstModule = Module::where('pack_id', $module->pack_id)
            ->active()
            ->orderBy('ordre')
            ->orderBy('id')
            ->first();

        if ($firstModule && $firstModule->id === $module->id) {
            return true;
        }

        return $module->isUnlockedFor($user);
    }

    private function verifyModuleAccess(EliteUser $user, Module $module): void
    {
        if (!$this->isModuleAccessible($user, $module)) {
            throw ValidationException::withMessages([
                'module' => ['Ce module est verrouillé. Vous devez terminer le module précédent pour y accéder.']
            ]);
        }
    }

    private function isLessonUnlocked(EliteUser $user, Lesson $lesson, Module $module): bool
    {
        if (!$this->isModuleAccessible($user, $module)) {
            return false;
        }

        $firstLesson = Lesson::where('module_id', $module->id)
            ->active()
            ->orderBy('ordre')
            ->orderBy('id')
            ->first();

        if ($firstLesson && $firstLesson->id === $lesson->id) {
            return true;
        }

        $previousLesson = Lesson::where('module_id', $module->id)
            ->where('ordre', '<', $lesson->ordre)
            ->active()
            ->orderByDesc('ordre')
            ->orderByDesc('id')
            ->first();

        if (!$previousLesson) {
            return true;
        }

        return LessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $previousLesson->id)
            ->where('completed', true)
            ->exists();
    }

    private function verifyLessonAccess(EliteUser $user, Lesson $lesson): void
    {
        if (!$lesson->module || !$this->isLessonUnlocked($user, $lesson, $lesson->module)) {
            throw ValidationException::withMessages([
                'lesson' => ['Cette leçon est verrouillée. Vous devez terminer la leçon précédente pour y accéder.']
            ]);
        }
    }

    private function verifyCourseSchedule(EliteUser $user, Lesson $lesson): void
    {
        if (!$user->partner_id || !$lesson->module) {
            return;
        }

        $schedule = CourseSchedule::where('partner_id', $user->partner_id)
            ->where('pack_id', $lesson->module->pack_id)
            ->where(fn ($query) => $query->where('lesson_id', $lesson->id)->orWhereNull('lesson_id'))
            ->where('active', true)
            ->latest('starts_at')
            ->first();

        if ($schedule && !$schedule->isOpen()) {
            throw ValidationException::withMessages([
                'lesson' => ['Ce cours sera disponible à partir du ' . $schedule->starts_at->format('d/m/Y H:i') . '.'],
            ]);
        }
    }

    private function verifyAllModuleLessonsCompleted(EliteUser $user, Module $module): void
    {
        $totalLessons = Lesson::where('module_id', $module->id)
            ->active()
            ->count();

        $completedLessons = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', Lesson::where('module_id', $module->id)->active()->pluck('id'))
            ->where('completed', true)
            ->count();

        if ($completedLessons < $totalLessons) {
            throw ValidationException::withMessages([
                'quiz' => ['Vous devez terminer toutes les leçons de ce module avant d\'accéder au quiz.']
            ]);
        }
    }

    private function getResultMessage(float $note, bool $reussi, string $action, Module $module): string
    {
        if ($reussi) {
            return "Excellent ! Vous avez obtenu {$note}/20. Le module suivant est maintenant débloqué.";
        }

        if ($action === 'parrainage') {
            $req = $module->parrainages_requis ?? 4;
            return "Vous avez obtenu {$note}/20. Pour continuer, parrainez {$req} personnes ou recommencez le quiz.";
        }

        $min = $module->note_parrainage ?? 10;
        return "Vous avez obtenu {$note}/20. La note minimale est de {$min}/20. Révisez et réessayez !";
    }

    // =========================================================================
    // ALIASES POUR MAINTENIR LA COMPATIBILITÉ AVEC L'ANCIENNE STRUCTURE
    // =========================================================================

    public function getModuleChapters(EliteUser $user, int $moduleId): array
    {
        $data = $this->getModuleLessons($user, $moduleId);
        return [
            [
                'id' => $data['module']['id'],
                'nom' => $data['module']['nom'],
                'description' => $data['module']['description'],
                'is_unlocked' => $data['module']['is_unlocked'],
                'is_completed' => $data['module']['is_completed'],
                'completed_count' => $data['module']['completed_count'],
                'lessons_count' => $data['module']['lessons_count'],
                'has_access' => $data['module']['has_access'],
                'note_passage' => $data['module']['note_passage'],
                'note_parrainage' => $data['module']['note_parrainage'],
                'parrainages_requis' => $data['module']['parrainages_requis'],
                'lessons' => $data['lessons'],
                'has_quiz' => $data['has_quiz'],
                'quiz' => $data['quiz'],
            ]
        ];
    }

    public function getChapterLessons(EliteUser $user, int $chapterId): array
    {
        // Si chapterId correspond à un moduleId, renvoyer les leçons
        $module = Module::find($chapterId);
        if ($module) {
            $data = $this->getModuleLessons($user, $moduleId = $chapterId);
            return $data['lessons'];
        }
        return [];
    }

    public function getChapterQuiz(EliteUser $user, int $chapterId): array
    {
        return $this->getModuleQuiz($user, $chapterId);
    }

    public function unlockChapterByReferral(EliteUser $user, int $chapterId): array
    {
        return $this->unlockModuleByReferral($user, $chapterId);
    }
}
