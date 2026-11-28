<?php

use App\Modules\Programmes\Http\Controllers\AcademicConfigController;
use App\Modules\Programmes\Http\Controllers\ProgrammeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('academic-config/{kind}', [AcademicConfigController::class, 'index'])->whereIn('kind', AcademicConfigController::KINDS);
    Route::post('academic-config/{kind}', [AcademicConfigController::class, 'store'])->whereIn('kind', AcademicConfigController::KINDS);
    Route::patch('academic-config/{kind}/{code}', [AcademicConfigController::class, 'update'])->whereIn('kind', AcademicConfigController::KINDS);

    Route::get('programmes', [ProgrammeController::class, 'index']);
    Route::post('programmes', [ProgrammeController::class, 'store']);
    Route::get('programmes/{programme}', [ProgrammeController::class, 'show']);
    Route::patch('programmes/{programme}', [ProgrammeController::class, 'update']);
});
