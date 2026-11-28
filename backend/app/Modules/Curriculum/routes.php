<?php

use App\Modules\Curriculum\Http\Controllers\CourseController;
use App\Modules\Curriculum\Http\Controllers\CurriculumController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('courses', [CourseController::class, 'index']);
    Route::post('courses', [CourseController::class, 'store']);
    Route::get('courses/{course}', [CourseController::class, 'show']);
    Route::patch('courses/{course}', [CourseController::class, 'update']);
    Route::post('courses/{course}/requisites', [CourseController::class, 'addRequisite']);
    Route::delete('courses/{course}/requisites/{requisite}', [CourseController::class, 'removeRequisite'])->whereNumber('requisite');

    Route::get('curricula', [CurriculumController::class, 'index']);
    Route::post('curricula', [CurriculumController::class, 'store']);
    Route::get('curricula/{curriculum}', [CurriculumController::class, 'show']);
    Route::patch('curricula/{curriculum}', [CurriculumController::class, 'update']);
    Route::post('curricula/{curriculum}/courses', [CurriculumController::class, 'addCourse']);
    Route::patch('curricula/{curriculum}/courses/{row}', [CurriculumController::class, 'updateCourse'])->whereNumber('row');
    Route::delete('curricula/{curriculum}/courses/{row}', [CurriculumController::class, 'removeCourse'])->whereNumber('row');
    Route::post('curricula/{curriculum}/elective-groups', [CurriculumController::class, 'addGroup']);
    Route::delete('curricula/{curriculum}/elective-groups/{group}', [CurriculumController::class, 'removeGroup'])->whereNumber('group');
    Route::post('curricula/{curriculum}/submit', [CurriculumController::class, 'submit']);
    Route::post('curricula/{curriculum}/publish', [CurriculumController::class, 'publish']);
    Route::post('curricula/{curriculum}/retire', [CurriculumController::class, 'retire']);
});
