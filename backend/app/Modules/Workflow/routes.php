<?php

use App\Modules\Workflow\Http\Controllers\WorkflowDefinitionController;
use App\Modules\Workflow\Http\Controllers\WorkflowInstanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('workflow-definitions', [WorkflowDefinitionController::class, 'index']);
    Route::post('workflow-definitions', [WorkflowDefinitionController::class, 'store']);
    Route::get('workflow-definitions/{definition}', [WorkflowDefinitionController::class, 'show']);
    Route::post('workflow-definitions/{definition}/retire', [WorkflowDefinitionController::class, 'retire']);

    Route::get('workflows/inbox', [WorkflowInstanceController::class, 'inbox']);
    Route::get('workflows/mine', [WorkflowInstanceController::class, 'mine']);
    Route::get('workflows/{instance}', [WorkflowInstanceController::class, 'show']);
    Route::post('workflows/{instance}/actions', [WorkflowInstanceController::class, 'act']);
});
