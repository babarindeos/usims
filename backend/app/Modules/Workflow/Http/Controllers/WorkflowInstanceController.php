<?php

namespace App\Modules\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Services\AccessService;
use App\Modules\Workflow\Http\Resources\WorkflowInstanceResource;
use App\Modules\Workflow\Models\WorkflowInstance;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class WorkflowInstanceController extends Controller
{
    public function __construct(private WorkflowEngine $engine, private AccessService $access) {}

    /** Items waiting for the signed-in user's decision. */
    public function inbox(Request $request): AnonymousResourceCollection
    {
        return WorkflowInstanceResource::collection($this->engine->pendingFor($request->user()));
    }

    /** Submissions the signed-in user started. */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $items = WorkflowInstance::with(['definition', 'currentStage', 'orgUnit', 'initiator'])
            ->where('initiated_by', $request->user()->id)->orderByDesc('id')->paginate(25);

        return WorkflowInstanceResource::collection($items);
    }

    /** Visible to the initiator and to anyone who can act on any stage of it. */
    public function show(Request $request, WorkflowInstance $instance): WorkflowInstanceResource
    {
        $instance->load(['definition.stages', 'currentStage', 'orgUnit', 'initiator', 'actions.user', 'actions.stage']);
        $user = $request->user();
        $canSee = $instance->initiated_by === $user->id
            || $instance->definition->stages->contains(fn ($s) => $this->access->allows($user, $s->permission_code, $instance));
        abort_unless($canSee, 403);

        return new WorkflowInstanceResource($instance);
    }

    public function act(Request $request, WorkflowInstance $instance): WorkflowInstanceResource
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(WorkflowEngine::ACTIONS)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $instance = $this->engine->act($instance, $request->user(), $data['action'], $data['remarks'] ?? null);

        return new WorkflowInstanceResource($instance->load(['orgUnit', 'initiator', 'actions.user', 'actions.stage']));
    }
}
