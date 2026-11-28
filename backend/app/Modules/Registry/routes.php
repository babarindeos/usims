<?php

use App\Modules\Registry\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('students', [StudentController::class, 'index']);
    Route::get('students/{student}', [StudentController::class, 'show']);
    Route::post('students/{student}/status', [StudentController::class, 'changeStatus']);
    Route::post('matriculations', [StudentController::class, 'matriculate']);
});
