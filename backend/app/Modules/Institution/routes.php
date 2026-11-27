<?php

use App\Modules\Institution\Http\Controllers\AcademicSessionController;
use App\Modules\Institution\Http\Controllers\CalendarEventController;
use App\Modules\Institution\Http\Controllers\OrgUnitController;
use App\Modules\Institution\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

Route::get('branding', [SettingController::class, 'branding']);

Route::middleware('auth:sanctum')->group(function () {
    Route::put('branding', [SettingController::class, 'updateBranding']);

    Route::get('org-unit-types', [OrgUnitController::class, 'types']);
    Route::get('org-units', [OrgUnitController::class, 'index']);
    Route::post('org-units', [OrgUnitController::class, 'store']);
    Route::get('org-units/{orgUnit}', [OrgUnitController::class, 'show']);
    Route::patch('org-units/{orgUnit}', [OrgUnitController::class, 'update']);

    Route::get('academic-sessions', [AcademicSessionController::class, 'index']);
    Route::post('academic-sessions', [AcademicSessionController::class, 'store']);
    Route::get('academic-sessions/{session}', [AcademicSessionController::class, 'show']);
    Route::patch('academic-sessions/{session}', [AcademicSessionController::class, 'update']);
    Route::post('academic-sessions/{session}/make-current', [AcademicSessionController::class, 'makeCurrent']);
    Route::post('academic-sessions/{session}/close', [AcademicSessionController::class, 'close']);
    Route::post('academic-sessions/{session}/semesters', [AcademicSessionController::class, 'storeSemester']);
    Route::patch('semesters/{semester}', [AcademicSessionController::class, 'updateSemester']);

    Route::get('calendar-events', [CalendarEventController::class, 'index']);
    Route::post('calendar-events', [CalendarEventController::class, 'store']);
    Route::patch('calendar-events/{event}', [CalendarEventController::class, 'update']);
    Route::delete('calendar-events/{event}', [CalendarEventController::class, 'destroy']);
});
