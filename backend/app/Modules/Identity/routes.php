<?php

use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\GrantController;
use App\Modules\Identity\Http\Controllers\PersonController;
use App\Modules\Identity\Http\Controllers\RoleController;
use App\Modules\Identity\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    Route::get('persons', [PersonController::class, 'index']);
    Route::post('persons/duplicates', [PersonController::class, 'duplicates']);
    Route::post('persons', [PersonController::class, 'store']);
    Route::get('persons/{person}', [PersonController::class, 'show']);
    Route::patch('persons/{person}', [PersonController::class, 'update']);

    Route::get('users', [UserController::class, 'index']);
    Route::post('users', [UserController::class, 'store']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::patch('users/{user}', [UserController::class, 'update']);

    Route::get('users/{user}/grants', [GrantController::class, 'index']);
    Route::post('users/{user}/grants', [GrantController::class, 'store']);
    Route::post('grants/{grant}/revoke', [GrantController::class, 'revoke']);

    Route::get('permissions', [RoleController::class, 'permissions']);
    Route::get('roles', [RoleController::class, 'index']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::get('roles/{role}', [RoleController::class, 'show']);
    Route::patch('roles/{role}', [RoleController::class, 'update']);
});
