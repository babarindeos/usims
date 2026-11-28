<?php

use App\Modules\Admissions\Http\Controllers\AdmissionConfigController;
use App\Modules\Admissions\Http\Controllers\AdmissionListController;
use App\Modules\Admissions\Http\Controllers\ApplicantController;
use App\Modules\Admissions\Http\Controllers\ExerciseController;
use App\Modules\Admissions\Http\Controllers\OfferAdminController;
use App\Modules\Admissions\Http\Controllers\ProcessingController;
use Illuminate\Support\Facades\Route;

// Public applicant portal entry points.
Route::post('applicant/register', [ApplicantController::class, 'register'])->middleware('throttle:10,1');
Route::get('applicant/verify/{user}/{hash}', [ApplicantController::class, 'verify'])->name('applicant.verify');
Route::get('admission-exercises/open', [ApplicantController::class, 'openExercises']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('applicant/verification-notice', [ApplicantController::class, 'resendVerification'])->middleware('throttle:3,1');
    Route::get('applicant/applications', [ApplicantController::class, 'index']);
    Route::post('applicant/applications', [ApplicantController::class, 'store']);
    Route::prefix('applicant/applications/{application}')->group(function () {
        Route::get('/', [ApplicantController::class, 'show']);
        Route::put('biodata', [ApplicantController::class, 'biodata']);
        Route::put('details', [ApplicantController::class, 'details']);
        Route::put('olevel', [ApplicantController::class, 'olevel']);
        Route::put('education', [ApplicantController::class, 'education']);
        Route::post('documents', [ApplicantController::class, 'upload']);
        Route::post('payments/{purpose}', [ApplicantController::class, 'pay'])->whereIn('purpose', ['application_fee', 'acceptance_fee']);
        Route::post('submit', [ApplicantController::class, 'submit']);
        Route::post('offer/accept', [ApplicantController::class, 'accept']);
        Route::post('offer/decline', [ApplicantController::class, 'decline']);
        Route::put('onboarding', [ApplicantController::class, 'onboarding']);
        Route::post('clearance/{item}/resubmit', [ApplicantController::class, 'resubmitClearance'])->whereNumber('item');
    });

    Route::get('admission-config/letter-template', [AdmissionConfigController::class, 'letterTemplate']);
    Route::put('admission-config/letter-template', [AdmissionConfigController::class, 'updateLetterTemplate']);
    Route::get('admission-config/{kind}', [AdmissionConfigController::class, 'index'])->whereIn('kind', AdmissionConfigController::KINDS);
    Route::post('admission-config/{kind}', [AdmissionConfigController::class, 'store'])->whereIn('kind', AdmissionConfigController::KINDS);
    Route::patch('admission-config/{kind}/{id}', [AdmissionConfigController::class, 'update'])->whereIn('kind', AdmissionConfigController::KINDS)->whereNumber('id');

    Route::get('admission-exercises', [ExerciseController::class, 'index']);
    Route::post('admission-exercises', [ExerciseController::class, 'store']);
    Route::get('admission-exercises/{exercise}', [ExerciseController::class, 'show']);
    Route::patch('admission-exercises/{exercise}', [ExerciseController::class, 'update']);
    Route::post('admission-exercises/{exercise}/status', [ExerciseController::class, 'changeStatus']);
    Route::post('admission-exercises/{exercise}/programmes', [ExerciseController::class, 'addProgramme']);
    Route::patch('admission-exercises/{exercise}/programmes/{row}', [ExerciseController::class, 'updateProgramme'])->whereNumber('row');
    Route::delete('admission-exercises/{exercise}/programmes/{row}', [ExerciseController::class, 'removeProgramme'])->whereNumber('row');
    Route::put('admission-exercises/{exercise}/documents', [ExerciseController::class, 'documents']);

    Route::get('admission-applications', [ProcessingController::class, 'index']);
    Route::get('admission-applications/export', [ProcessingController::class, 'export']);
    Route::get('admission-applications/{application}', [ProcessingController::class, 'show']);
    Route::post('admission-applications/{application}/{action}', [ProcessingController::class, 'act'])
        ->whereIn('action', ['screen', 'recommend', 'hold', 'reject', 'change-programme', 'open-corrections', 'close-corrections']);
    Route::get('admission-documents/{document}', [ProcessingController::class, 'download']);
    Route::patch('admission-documents/{document}', [ProcessingController::class, 'reviewDocument']);
    Route::get('admission-payments', [ProcessingController::class, 'payments']);
    Route::post('admission-payments/{payment}/review', [ProcessingController::class, 'reviewPayment']);

    Route::get('admission-lists', [AdmissionListController::class, 'index']);
    Route::post('admission-lists', [AdmissionListController::class, 'store']);
    Route::get('admission-lists/{list}', [AdmissionListController::class, 'show']);
    Route::post('admission-lists/{list}/entries', [AdmissionListController::class, 'addEntries']);
    Route::delete('admission-lists/{list}/entries/{application}', [AdmissionListController::class, 'removeEntry']);
    Route::post('admission-lists/{list}/submit', [AdmissionListController::class, 'submit']);

    Route::get('admission-offers', [OfferAdminController::class, 'index']);
    Route::get('admission-offers/{offer}', [OfferAdminController::class, 'show']);
    Route::get('clearance-items', [OfferAdminController::class, 'clearanceQueue']);
    Route::post('clearance-items/{item}/review', [OfferAdminController::class, 'reviewClearance'])->whereNumber('item');
});
