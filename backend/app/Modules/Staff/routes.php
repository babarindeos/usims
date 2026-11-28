<?php

use App\Modules\Staff\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('staff', [StaffController::class, 'index']);
    Route::post('staff', [StaffController::class, 'store']);
    Route::get('staff/{staff}', [StaffController::class, 'show']);
    Route::patch('staff/{staff}', [StaffController::class, 'update']);
});
