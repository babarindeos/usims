<?php

namespace App\Modules\Institution\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Services\AccessService;
use App\Modules\Institution\Http\Requests\CalendarEventRequest;
use App\Modules\Institution\Http\Resources\CalendarEventResource;
use App\Modules\Institution\Models\CalendarEvent;
use App\Modules\Institution\Models\OrgUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CalendarEventController extends Controller
{
    public function __construct(private AccessService $access) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['academic_session_id' => ['nullable', 'integer'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $events = CalendarEvent::query()->with('orgUnit')
            ->when($request->query('academic_session_id'), fn ($q, $id) => $q->where('academic_session_id', $id))
            ->when($request->query('from'), fn ($q, $d) => $q->where('ends_on', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->where('starts_on', '<=', $d))
            ->orderBy('starts_on')
            ->limit(500)->get();

        return CalendarEventResource::collection($events);
    }

    public function store(CalendarEventRequest $request): JsonResponse
    {
        $unit = $this->unit($request);
        $this->authorizeFor($request, $unit);

        $event = CalendarEvent::create($request->safe()->except('org_unit_id') + [
            'org_unit_id' => $unit?->id, 'created_by' => $request->user()->id,
        ]);

        return (new CalendarEventResource($event->load('orgUnit')))->response()->setStatusCode(201);
    }

    public function update(CalendarEventRequest $request, CalendarEvent $event): CalendarEventResource
    {
        $this->authorizeFor($request, $event->orgUnit);
        $data = $request->safe()->except('org_unit_id');
        if ($request->has('org_unit_id')) {
            $unit = $this->unit($request);
            $this->authorizeFor($request, $unit);
            $data['org_unit_id'] = $unit?->id;
        }
        $event->update($data);

        return new CalendarEventResource($event->load('orgUnit'));
    }

    public function destroy(Request $request, CalendarEvent $event): JsonResponse
    {
        $this->authorizeFor($request, $event->orgUnit);
        $event->delete();

        return response()->json(null, 204);
    }

    private function unit(Request $request): ?OrgUnit
    {
        return $request->input('org_unit_id') ? OrgUnit::where('uuid', $request->input('org_unit_id'))->firstOrFail() : null;
    }

    /** University-wide events need global calendar.manage; unit events need it over that unit. */
    private function authorizeFor(Request $request, ?OrgUnit $unit): void
    {
        $allowed = $unit ? Gate::allows('calendar.manage', $unit) : $this->access->allowsGlobally($request->user(), 'calendar.manage');
        abort_unless($allowed, 403, 'You cannot manage calendar events at this level.');
    }
}
