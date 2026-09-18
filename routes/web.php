<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\PackController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\ChapterController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\QuizController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\CorrespondenceController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\ComptabiliteController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\Web\RegisterController;
use App\Http\Controllers\PartnerWebAuthController;
use App\Http\Controllers\PartnerWebController;
use App\Http\Controllers\Admin\PartnerController as AdminPartnerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Admin Panel
|--------------------------------------------------------------------------
*/

Route::get('project_elite_tfs237', function () {
    return view('welcome');
});

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

Route::prefix('partenaire')->name('partner.')->group(function () {
    Route::get('/login', [PartnerWebAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [PartnerWebAuthController::class, 'login'])->name('login.submit');
    Route::middleware('auth:partner_web')->group(function () {
        Route::get('/', [PartnerWebController::class, 'dashboard'])->name('dashboard');
        Route::get('/comptabilite', [PartnerWebController::class, 'comptabilite'])->name('comptabilite');
        Route::get('/comptabilite/rapport', [PartnerWebController::class, 'globalReport'])->name('comptabilite.report');
        Route::get('/comptabilite/apprenant/{learner}', [PartnerWebController::class, 'learnerReport'])->name('comptabilite.learner');
        Route::post('/installments/{installment}/pay-counter', [PartnerWebController::class, 'markInstallmentPaid'])->name('installments.pay-counter');
        Route::get('/plans', [PartnerWebController::class, 'plans'])->name('plans');
        Route::post('/plans', [PartnerWebController::class, 'savePlan'])->name('plans.save');
        Route::delete('/plans/{plan}', [PartnerWebController::class, 'deletePlan'])->name('plans.delete');
        Route::get('/horaires', [PartnerWebController::class, 'schedules'])->name('schedules');
        Route::post('/horaires', [PartnerWebController::class, 'saveSchedule'])->name('schedules.save');
        Route::delete('/horaires/{schedule}', [PartnerWebController::class, 'deleteSchedule'])->name('schedules.delete');
        Route::get('/centres', [PartnerWebController::class, 'centres'])->name('centres');
        Route::post('/logout', [PartnerWebAuthController::class, 'logout'])->name('logout');
    });
});

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('partners', [AdminPartnerController::class, 'index'])->name('partners.index');
    Route::post('partners', [AdminPartnerController::class, 'store'])->name('partners.store');
    Route::patch('partners/{partner}/toggle', [AdminPartnerController::class, 'toggle'])->name('partners.toggle');

    // Users Management
    Route::resource('users', UserController::class)->except(['create', 'store']);
    Route::post('users/{user}/add-points', [UserController::class, 'addPoints'])->name('users.add-points');

    Route::resource('categories', CategoryController::class);

    // Packs & Courses Management
    Route::resource('packs', PackController::class);
    Route::post('packs/{pack}/toggle-active', [PackController::class, 'toggleActive'])->name('packs.toggle-active');

    // Modules
    Route::get('packs/{pack}/modules/create', [ModuleController::class, 'create'])->name('modules.create');
    Route::post('packs/{pack}/modules', [ModuleController::class, 'store'])->name('modules.store');
    Route::get('modules/{module}', [ModuleController::class, 'show'])->name('modules.show');
    Route::get('modules/{module}/edit', [ModuleController::class, 'edit'])->name('modules.edit');
    Route::put('modules/{module}', [ModuleController::class, 'update'])->name('modules.update');
    Route::delete('modules/{module}', [ModuleController::class, 'destroy'])->name('modules.destroy');

    // Lessons (Directly under Module)
    Route::get('modules/{module}/lessons/create', [LessonController::class, 'create'])->name('lessons.create');
    Route::post('modules/{module}/lessons', [LessonController::class, 'store'])->name('lessons.store');
    Route::get('lessons/{lesson}/edit', [LessonController::class, 'edit'])->name('lessons.edit');
    Route::get('lessons/{lesson}/video/{part}', [LessonController::class, 'videoPreview'])
        ->whereIn('part', ['explication', 'pratique'])->name('lessons.video-preview');
    Route::put('lessons/{lesson}', [LessonController::class, 'update'])->name('lessons.update');
    Route::delete('lessons/{lesson}', [LessonController::class, 'destroy'])->name('lessons.destroy');

    // Quizzes (Directly under Module)
    Route::get('modules/{module}/quiz/create', [QuizController::class, 'create'])->name('quizzes.create');
    Route::post('modules/{module}/quiz', [QuizController::class, 'store'])->name('quizzes.store');
    Route::get('quizzes/{quiz}', [QuizController::class, 'show'])->name('quizzes.show');
    Route::get('quizzes/{quiz}/edit', [QuizController::class, 'edit'])->name('quizzes.edit');
    Route::put('quizzes/{quiz}', [QuizController::class, 'update'])->name('quizzes.update');
    Route::delete('quizzes/{quiz}', [QuizController::class, 'destroy'])->name('quizzes.destroy');
    Route::post('quizzes/{quiz}/questions', [QuizController::class, 'addQuestion'])->name('quizzes.add-question');
    Route::put('questions/{question}', [QuizController::class, 'updateQuestion'])->name('questions.update');
    Route::delete('questions/{question}', [QuizController::class, 'deleteQuestion'])->name('questions.destroy');

    // Chapters (Legacy Compatibility)
    Route::get('chapters/{chapter}', [ChapterController::class, 'show'])->name('chapters.show');
    Route::get('chapters/{chapter}/edit', [ChapterController::class, 'edit'])->name('chapters.edit');
    Route::put('chapters/{chapter}', [ChapterController::class, 'update'])->name('chapters.update');
    Route::delete('chapters/{chapter}', [ChapterController::class, 'destroy'])->name('chapters.destroy');

    // Career Profiles
    Route::resource('profiles', ProfileController::class);

    // Correspondence
    Route::get('correspondence', [CorrespondenceController::class, 'index'])->name('correspondence.index');
    Route::get('correspondence/categories/create', [CorrespondenceController::class, 'createCategory'])->name('correspondence.create-category');
    Route::post('correspondence/categories', [CorrespondenceController::class, 'storeCategory'])->name('correspondence.store-category');
    Route::get('correspondence/categories/{category}/questions/create', [CorrespondenceController::class, 'createQuestion'])->name('correspondence.create-question');
    Route::post('correspondence/categories/{category}/questions', [CorrespondenceController::class, 'storeQuestion'])->name('correspondence.store-question');
    Route::get('correspondence/questions/{question}/edit', [CorrespondenceController::class, 'editQuestion'])->name('correspondence.edit-question');
    Route::put('correspondence/questions/{question}', [CorrespondenceController::class, 'updateQuestion'])->name('correspondence.update-question');
    Route::delete('correspondence/questions/{question}', [CorrespondenceController::class, 'deleteQuestion'])->name('correspondence.delete-question');

    // Transactions & Payments
    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('transactions/settings', [TransactionController::class, 'settings'])->name('transactions.settings');
    Route::put('transactions/settings/update', [TransactionController::class, 'updateSettings'])->name('transactions.update-settings');
    Route::get('cash-codes', [TransactionController::class, 'cashCodes'])->name('cash-codes.index');
    Route::get('cash-codes/create', [TransactionController::class, 'createCashCode'])->name('cash-codes.create');
    Route::post('cash-codes', [TransactionController::class, 'storeCashCode'])->name('cash-codes.store');
    Route::delete('cash-codes/{cashCode}', [TransactionController::class, 'deleteCashCode'])->name('cash-codes.destroy');
    Route::get('settings/payments', [TransactionController::class, 'settings'])->name('settings.payments');
    Route::put('settings/payments', [TransactionController::class, 'updateSettings'])->name('settings.payments.update');

    // Comptabilité & Suivi des Apprenants
    Route::get('comptabilite', [ComptabiliteController::class, 'index'])->name('comptabilite.index');
    Route::get('comptabilite/rapport', [ComptabiliteController::class, 'report'])->name('comptabilite.report');
    Route::get('comptabilite/apprenant/{user}', [ComptabiliteController::class, 'learnerReport'])->name('comptabilite.learner');
});

// ─── Routes publiques : soumission d'offres ───
Route::prefix('soumettre')->name('public.submit.')->group(function () {
    Route::get('/', [OpportunityController::class, 'publicSubmitForm'])->name('form');
    Route::post('/emploi', [OpportunityController::class, 'publicSubmitJob'])->name('job');
    Route::post('/concours', [OpportunityController::class, 'publicSubmitConcours'])->name('concours');
    Route::post('/financement', [OpportunityController::class, 'publicSubmitFinancement'])->name('financement');

    Route::get('/livre', [OpportunityController::class, 'publicBookForm'])->name('book-form');
    Route::post('/livre', [OpportunityController::class, 'publicSubmitBook'])->name('book');
});

// ─── Routes Elite Users : consultation ───
Route::prefix('espace')->name('elite.')->group(function () {
    Route::get('/emplois-concours', [OpportunityController::class, 'emploisConcours'])->name('emplois-concours');
    Route::get('/financements', [OpportunityController::class, 'financements'])->name('financements');
    Route::get('/bibliotheque', [OpportunityController::class, 'bibliotheque'])->name('bibliotheque');
    Route::get('/bibliotheque/{livre}', [OpportunityController::class, 'viewBook'])->name('bibliotheque.view');
    Route::get('/bibliotheque/{livre}/download', [OpportunityController::class, 'downloadBook'])->name('bibliotheque.download');
    Route::get('/communaute', [OpportunityController::class, 'communaute'])->name('communaute');
});

Route::prefix('register')->name('web.')->group(function () {
    Route::get('/', [RegisterController::class, 'showRegisterForm'])->name('register');
    Route::post('/', [RegisterController::class, 'register'])->name('register.submit');

    // OTP
    Route::post('/send-otp', [RegisterController::class, 'sendOtp'])->name('send-otp');
    Route::post('/verify-otp', [RegisterController::class, 'verifyOtp'])->name('verify-otp');

    // Google OAuth
    Route::get('/auth/google', [RegisterController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('/auth/google/callback', [RegisterController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});


// Auth routes (use Laravel Breeze or Fortify)
require __DIR__.'/auth.php';
