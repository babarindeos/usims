<?php

use App\Modules\Offerings\Http\Controllers\OfferingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('offerings', [OfferingController::class, 'index']);
    Route::post('offerings', [OfferingController::class, 'store']);
    Route::post('semesters/{semester}/offerings/generate', [OfferingController::class, 'generate']);
    Route::get('offerings/{offering}', [OfferingController::class, 'show']);
    Route::patch('offerings/{offering}', [OfferingController::class, 'update']);
    Route::post('offerings/{offering}/publish', [OfferingController::class, 'publish']);
    Route::post('offerings/{offering}/cancel', [OfferingController::class, 'cancel']);
    Route::post('offerings/{offering}/programmes', [OfferingController::class, 'addProgramme']);
    Route::delete('offerings/{offering}/programmes/{link}', [OfferingController::class, 'removeProgramme'])->whereNumber('link');
    Route::post('offerings/{offering}/staff', [OfferingController::class, 'assignStaff']);
    Route::post('offerings/{offering}/staff/{assignment}/end', [OfferingController::class, 'endAssignment'])->whereNumber('assignment');
});
