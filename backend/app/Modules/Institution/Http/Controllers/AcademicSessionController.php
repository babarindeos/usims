<?php

namespace App\Modules\Institution\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Institution\Http\Requests\AcademicSessionRequest;
use App\Modules\Institution\Http\Requests\SemesterRequest;
use App\Modules\Institution\Http\Resources\AcademicSessionResource;
use App\Modules\Institution\Http\Resources\SemesterResource;
use App\Modules\Institution\Models\AcademicSession;
use App\Modules\Institution\Models\Semester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AcademicSessionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AcademicSessionResource::collection(AcademicSession::with('semesters')->orderByDesc('starts_on')->get());
    }

    public function show(AcademicSession $session): AcademicSessionResource
    {
        return new AcademicSessionResource($session->load('semesters'));
    }

    public function store(AcademicSessionRequest $request): JsonResponse
    {
        Gate::authorize('sessions.manage');
        $session = AcademicSession::create($this->attributes($request));

        return (new AcademicSessionResource($session->load('semesters')))->response()->setStatusCode(201);
    }

    public function update(AcademicSessionRequest $request, AcademicSession $session): AcademicSessionResource
    {
        Gate::authorize('sessions.manage');
        $this->ensureOpen($session);
        $session->update($this->attributes($request));

        return new AcademicSessionResource($session->load('semesters'));
    }

    public function makeCurrent(AcademicSession $session): AcademicSessionResource
    {
        Gate::authorize('sessions.manage');
        $this->ensureOpen($session);
        DB::transaction(function () use ($session) {
            AcademicSession::where('is_current', true)->where('id', '!=', $session->id)->get()->each->update(['is_current' => false]);
            $session->update(['is_current' => true]);
        });

        return new AcademicSessionResource($session->load('semesters'));
    }

    /** Closing locks the session; every process is closed with it. */
    public function close(Request $request, AcademicSession $session): AcademicSessionResource
    {
        Gate::authorize('sessions.manage');
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->ensureOpen($session);

        $session->forceFill(array_fill_keys(AcademicSession::PROCESSES, 'closed') + [
            'closed_at' => now(), 'closed_by' => $request->user()->id, 'is_current' => false,
        ])->save();

        return new AcademicSessionResource($session->load('semesters'));
    }

    public function storeSemester(SemesterRequest $request, AcademicSession $session): JsonResponse
    {
        Gate::authorize('sessions.manage');
        $this->ensureOpen($session);
        $this->ensureUnique($session, $request);
        $this->ensureInside($session, $request->validated());
        $semester = $session->semesters()->create($request->validated());

        return (new SemesterResource($semester))->response()->setStatusCode(201);
    }

    public function updateSemester(SemesterRequest $request, Semester $semester): SemesterResource
    {
        Gate::authorize('sessions.manage');
        $this->ensureOpen($semester->session);
        $this->ensureInside($semester->session, $request->validated() + $semester->only(['starts_on', 'ends_on']));
        $semester->update($request->validated());

        return new SemesterResource($semester);
    }

    private function attributes(AcademicSessionRequest $request): array
    {
        $data = $request->safe()->except('processes');
        foreach ($request->validated('processes', []) as $process => $status) {
            $data[$process.'_status'] = $status;
        }

        return $data;
    }

    private function ensureOpen(AcademicSession $session): void
    {
        if ($session->isClosed()) {
            throw ValidationException::withMessages(['session' => 'This session is closed and can no longer be changed.']);
        }
    }

    private function ensureUnique(AcademicSession $session, SemesterRequest $request): void
    {
        if ($session->semesters()->where(fn ($q) => $q->where('code', $request->validated('code'))->orWhere('sequence', $request->validated('sequence')))->exists()) {
            throw ValidationException::withMessages(['code' => 'A semester with this code or sequence already exists in the session.']);
        }
    }

    private function ensureInside(AcademicSession $session, array $data): void
    {
        $starts = Carbon::parse($data['starts_on']);
        $ends = Carbon::parse($data['ends_on']);
        if ($starts->lt($session->starts_on) || $ends->gt($session->ends_on)) {
            throw ValidationException::withMessages(['starts_on' => 'Semester dates must fall within the session dates.']);
        }
    }
}
