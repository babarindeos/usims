<?php

namespace App\Modules\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Workflow\Http\Requests\WorkflowDefinitionRequest;
use App\Modules\Workflow\Models\WorkflowDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Definitions are versioned by replacement: to change an approval chain, create a new definition and
 * retire the old one. Instances already running keep the stages they started with.
 */
class WorkflowDefinitionController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('workflows.manage');

        return response()->json(['data' => WorkflowDefinition::with('stages')->orderBy('process')->orderByDesc('priority')->get()]);
    }

    public function show(WorkflowDefinition $definition): JsonResponse
    {
        Gate::authorize('workflows.manage');

        return response()->json(['data' => $definition->load('stages')]);
    }

    public function store(WorkflowDefinitionRequest $request): JsonResponse
    {
        Gate::authorize('workflows.manage');
        $definition = DB::transaction(function () use ($request) {
            $definition = WorkflowDefinition::create($request->safe()->except('stages') + ['status' => 'active']);
            foreach ($request->validated('stages') as $i => $stage) {
                $definition->stages()->create($stage + ['sequence' => $i + 1]);
            }

            return $definition;
        });

        return response()->json(['data' => $definition->load('stages')], 201);
    }

    public function retire(WorkflowDefinition $definition): JsonResponse
    {
        Gate::authorize('workflows.manage');
        $definition->update(['status' => 'retired']);

        return response()->json(['data' => $definition->load('stages')]);
    }
}
