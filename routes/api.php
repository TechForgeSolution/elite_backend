<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CorrespondenceController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TrialController;
use App\Http\Controllers\Api\PartnerAuthController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\InstallmentController;
use App\Http\Controllers\Api\OpportunityController as ApiOpportunityController;
use App\Http\Controllers\Api\PublicCourseController;
use Illuminate\Support\Facades\Route;

// ============================================
// TEST
// ============================================
Route::get('/test', fn() => response()->json(['message' => 'Ghost API is working!']));

// ============================================
// AUTH — PUBLIC ROUTES
// ============================================
Route::prefix('auth')->group(function () {

    // ── Inscription ────────────────────────────────────────────
    Route::post('/register',            [AuthController::class, 'register']);      // ✅ une seule ligne
    Route::post('/login',               [AuthController::class, 'login']);
    Route::post('/social-login',        [AuthController::class, 'socialLogin']);
    Route::post('/check-referral-code', [AuthController::class, 'checkReferralCode']);
    Route::post('/verify-email-otp',    [AuthController::class, 'verifyEmailOtp']);
    Route::post('/resend-email-otp',    [AuthController::class, 'resendEmailOtp']);
});

// ============================================
// PUBLIC — PROFILES, PACKS, FAQ
// ============================================
Route::get('/profiles',          [ProfileController::class, 'index']);
Route::get('/profiles/secteurs', [ProfileController::class, 'secteurs']);
Route::get('/profiles/{id}',     [ProfileController::class, 'show']);

Route::get('/categories',        [CourseController::class, 'categories']);
Route::get('/packs',             [CourseController::class, 'packs']);
Route::get('/packs/{id}',        [CourseController::class, 'packDetails'])->where('id', '[0-9]+');
Route::get('/public/courses/digital', [PublicCourseController::class, 'digital']);
Route::get('/public/lessons/{lesson}/theory', [PublicCourseController::class, 'theory'])->where('lesson', '[0-9]+');
Route::get('/public/lessons/{lesson}/video/{part}', [PublicCourseController::class, 'video'])
    ->where(['lesson' => '[0-9]+', 'part' => 'pratique|explication'])
    ->middleware('signed:relative')
    ->name('public.lesson-video');

Route::get('/faq',               [FaqController::class, 'index']);
Route::get('/faq/search',        [FaqController::class, 'search']);
Route::get('/faq/popular',       [FaqController::class, 'popular']);
Route::get('/faq/{id}',          [FaqController::class, 'show']);

// ============================================
// PAYMENT — WEBHOOK (no auth)
// ============================================
Route::post('/payment/webhook',  [PaymentController::class, 'webhook'])->name('payment.webhook');
Route::get('/payment/return',    [PaymentController::class, 'returnUrl'])->name('payment.return');
Route::post('/partner/login', [PartnerAuthController::class, 'login']);

// ============================================
// AUTHENTICATED ROUTES
// ============================================
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::delete('/delete-account', [AuthController::class, 'deleteAccount']);
        Route::post('/logout',                 [AuthController::class, 'logout']);
        Route::get('/profile',                 [AuthController::class, 'profile']);
        Route::put('/profile',                 [AuthController::class, 'updateProfile']);
        Route::post('/complete-social-profile',[AuthController::class, 'completeSocialProfile']);
   
    });

    // Trial
    Route::get('/trial/status',    [TrialController::class, 'status']);
    Route::post('/trial/start',    [TrialController::class, 'start']);
    Route::post('/trial/activate', [TrialController::class, 'activate']);

    // Correspondence
    Route::prefix('correspondence')->group(function () {
        Route::get('/questions',         [CorrespondenceController::class, 'questions']);
        Route::post('/submit',           [CorrespondenceController::class, 'submit']);
        Route::get('/results',           [CorrespondenceController::class, 'results']);
        Route::post('/choose-profile',   [CorrespondenceController::class, 'chooseProfile']);
        Route::post('/choose-path',      [CorrespondenceController::class, 'choosePath']);
    });

    // Profiles & Roadmaps
    Route::get('/profiles/{id}/roadmap', [ProfileController::class, 'roadmap']);
    Route::get('/my-roadmap',            [ProfileController::class, 'myRoadmap']);

    // Courses (Module -> Lesson + Quiz architecture)
    Route::get('/packs/recommended',                       [CourseController::class, 'recommendedPacks']);
    Route::get('/packs/{id}/modules',                      [CourseController::class, 'packModules'])->where('id', '[0-9]+');
    Route::get('/modules/{id}/lessons',                    [CourseController::class, 'moduleLessons'])->where('id', '[0-9]+');
    Route::get('/modules/{id}/quiz',                       [CourseController::class, 'moduleQuiz'])->where('id', '[0-9]+');
    Route::get('/modules/{id}/quiz-info',                  [CourseController::class, 'getModuleQuizInfo'])->where('id', '[0-9]+');
    Route::post('/modules/{id}/unlock-by-referral',        [CourseController::class, 'unlockModuleByReferral'])->where('id', '[0-9]+');

    // Compatibility routes
    Route::get('/modules/{id}/chapters',                   [CourseController::class, 'moduleChapters'])->where('id', '[0-9]+');
    Route::get('/chapters/{id}/lessons',                   [CourseController::class, 'chapterLessons'])->where('id', '[0-9]+');
    Route::get('/chapters/{id}/quiz',                      [CourseController::class, 'chapterQuiz'])->where('id', '[0-9]+');
    Route::get('/chapters/{id}/quiz-info',                 [CourseController::class, 'getChapterQuizInfo']);
    Route::post('/chapters/{id}/unlock-by-referral',       [CourseController::class, 'unlockByReferral'])->where('id', '[0-9]+');

    Route::get('/lessons/{id}',                            [CourseController::class, 'lesson'])->where('id', '[0-9]+');
    Route::post('/lessons/{id}/complete',                  [CourseController::class, 'completeLesson'])->where('id', '[0-9]+');
    Route::post('/quiz/{id}/submit',                       [CourseController::class, 'submitQuiz'])->where('id', '[0-9]+');

    // Wallet
    Route::prefix('wallet')->group(function () {
        Route::get('/balance',       [WalletController::class, 'balance']);
        Route::post('/use-cash-code',[WalletController::class, 'useCashCode']);
        Route::post('/find-user',    [WalletController::class, 'findUser']);
        Route::post('/transfer',     [WalletController::class, 'transfer']);
        Route::get('/transactions',  [WalletController::class, 'transactions']);
    });
    Route::post('/packs/{id}/purchase', [WalletController::class, 'purchasePack'])->where('id', '[0-9]+');
    Route::get('/user/packs',           [WalletController::class, 'myPacks']);
    Route::get('/opportunities', [ApiOpportunityController::class, 'index']);
    Route::get('/payments/installments', [InstallmentController::class, 'index']);
    Route::post('/payments/installments/{installment}/pay', [InstallmentController::class, 'pay']);

    // Referral
    Route::prefix('referral')->group(function () {
        Route::get('/my-code',             [ReferralController::class, 'myCode']);
        Route::get('/stats',               [ReferralController::class, 'stats']);
        Route::get('/history',             [ReferralController::class, 'history']);
        Route::get('/my-parrain',          [ReferralController::class, 'myParrain']);
        Route::post('/create-project',     [ReferralController::class, 'createProject']);
        Route::post('/request-withdrawal', [ReferralController::class, 'requestWithdrawal']);
        Route::post('/confirm-withdrawal', [ReferralController::class, 'confirmWithdrawal']);
    });

    // Payment (authenticated)
    Route::prefix('payment')->group(function () {
        Route::post('/initiate-pack',    [PaymentController::class, 'initiatePackPayment']);
        Route::post('/initiate-deposit', [PaymentController::class, 'initiateDeposit']);
        Route::post('/check-status',     [PaymentController::class, 'checkPaymentStatus']);
    });
});

Route::middleware('auth:partner_sanctum')->prefix('partner')->group(function () {
    Route::post('/logout', [PartnerAuthController::class, 'logout']);
    Route::get('/dashboard', [PartnerController::class, 'dashboard']);
    Route::get('/centres', [PartnerController::class, 'centres']);
    Route::get('/plans', [PartnerController::class, 'plans']);
    Route::post('/plans', [PartnerController::class, 'savePlan']);
    Route::get('/schedules', [PartnerController::class, 'schedules']);
    Route::post('/schedules', [PartnerController::class, 'saveSchedule']);
});
