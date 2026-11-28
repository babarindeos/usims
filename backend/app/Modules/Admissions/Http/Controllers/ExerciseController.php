<?php

namespace App\Modules\Admissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admissions\Http\Resources\ExerciseResource;
use App\Modules\Admissions\Models\AdmissionCategory;
use App\Modules\Admissions\Models\AdmissionExercise;
use App\Modules\Admissions\Models\DocumentType;
use App\Modules\Admissions\Models\ExerciseProgramme;
use App\Modules\Admissions\Services\ScreeningEngine;
use App\Modules\Audit\Services\AuditContext;
use App\Modules\Programmes\Models\Programme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Registry opens and configures admission exercises (PRD 4.3, 5.2, scenario 1). */
class ExerciseController extends Controller
{
    private const DETAIL = ['session', 'category', 'programmes.programme.orgUnit', 'documents.documentType'];

    /** Status changes Registry may make by hand. Scheduled exercises open and close on their dates. */
    private const TRANSITIONS = [
        'draft' => ['scheduled', 'open'], 'scheduled' => ['draft', 'open', 'suspended', 'closed'],
        'open' => ['suspended', 'closed'], 'suspended' => ['open', 'closed'], 'closed' => [],
    ];

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('admissions.view');

        return ExerciseResource::collection(AdmissionExercise::with(['session', 'category'])->withCount('applications')->orderByDesc('opens_at')->get());
    }

    public function show(AdmissionExercise $exercise): ExerciseResource
    {
        Gate::authorize('admissions.view');

        return new ExerciseResource($exercise->load(self::DETAIL)->loadCount('applications'));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('admissions.configure');
        $data = $this->validated($request, true);
        $exercise = AdmissionExercise::create($data + ['status' => 'draft', 'created_by' => $request->user()->id]);

        return (new ExerciseResource($exercise->load(self::DETAIL)))->response()->setStatusCode(201);
    }

    public function update(Request $request, AdmissionExercise $exercise): ExerciseResource
    {
        Gate::authorize('admissions.configure');
        if ($exercise->status === 'closed') {
            throw ValidationException::withMessages(['exercise' => 'A closed exercise cannot be changed.']);
        }
        $data = $this->validated($request, false);
        if ($exercise->applications()->whereNot('status', 'draft')->exists() && array_intersect(array_keys($data), ['admission_category_id', 'academic_session_id', 'application_fee'])) {
            throw ValidationException::withMessages(['exercise' => 'Category, session and application fee are fixed once applications have been submitted.']);
        }
        $exercise->update($data);

        return new ExerciseResource($exercise->load(self::DETAIL));
    }

    public function changeStatus(Request $request, AdmissionExercise $exercise): ExerciseResource
    {
        Gate::authorize('admissions.configure');
        $data = $request->validate(['status' => ['required', Rule::in(AdmissionExercise::STATUSES)], 'reason' => ['required', 'string', 'max:500']]);
        if (! in_array($data['status'], self::TRANSITIONS[$exercise->status], true)) {
            throw ValidationException::withMessages(['status' => "A {$exercise->status} exercise cannot be set to {$data['status']}."]);
        }
        if (in_array($data['status'], ['open', 'scheduled'], true) && ! $exercise->programmes()->where('active', true)->exists()) {
            throw ValidationException::withMessages(['status' => 'Add at least one programme before opening the exercise.']);
        }
        app(AuditContext::class)->withReason($data['reason'], fn () => $exercise->update(['status' => $data['status']]));

        return new ExerciseResource($exercise->load(self::DETAIL));
    }

    public function addProgramme(Request $request, AdmissionExercise $exercise): JsonResponse
    {
        Gate::authorize('admissions.configure');
        $data = $request->validate([
            'programme_id' => ['required', 'string', 'exists:programmes,uuid'],
            'entry_level' => ['required', 'integer', 'between:100,900', 'multiple_of:100'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ] + $this->ruleValidation());
        $programme = Programme::where('uuid', $data['programme_id'])->firstOrFail();
        if ($programme->status !== 'active') {
            throw ValidationException::withMessages(['programme_id' => 'Only active programmes can admit.']);
        }
        if (! $programme->entryLevels()->where('level', $data['entry_level'])->exists()) {
            throw ValidationException::withMessages(['entry_level' => "{$programme->code} does not admit at {$data['entry_level']} level."]);
        }
        if ($exercise->programmes()->where(['programme_id' => $programme->id, 'entry_level' => $data['entry_level']])->exists()) {
            throw ValidationException::withMessages(['programme_id' => 'This programme and level are already in the exercise.']);
        }
        $exercise->programmes()->create(['programme_id' => $programme->id, 'entry_level' => $data['entry_level'], 'capacity' => $data['capacity'] ?? null, 'rules' => $this->rules($data)]);

        return (new ExerciseResource($exercise->load(self::DETAIL)))->response()->setStatusCode(201);
    }

    /** Rule changes apply to future screening runs; earlier results keep the rules they used. */
    public function updateProgramme(Request $request, AdmissionExercise $exercise, int $row): ExerciseResource
    {
        Gate::authorize('admissions.configure');
        $choice = ExerciseProgramme::where('admission_exercise_id', $exercise->id)->findOrFail($row);
        $data = $request->validate(['capacity' => ['nullable', 'integer', 'min:1'], 'active' => ['sometimes', 'boolean']] + $this->ruleValidation());
        $update = array_intersect_key($data, array_flip(['capacity', 'active']));
        if ($request->has('rules')) {
            $update['rules'] = $this->rules($data);
        }
        $choice->update($update);

        return new ExerciseResource($exercise->load(self::DETAIL));
    }

    public function removeProgramme(AdmissionExercise $exercise, int $row): Response
    {
        Gate::authorize('admissions.configure');
        $choice = ExerciseProgramme::where('admission_exercise_id', $exercise->id)->findOrFail($row);
        $used = DB::table('applications')->where('exercise_programme_id', $choice->id)->orWhere('second_choice_id', $choice->id)->exists();
        if ($used) {
            throw ValidationException::withMessages(['programme' => 'Applicants have chosen this programme; deactivate it instead.']);
        }
        $choice->delete();

        return response()->noContent();
    }

    public function documents(Request $request, AdmissionExercise $exercise): ExerciseResource
    {
        Gate::authorize('admissions.configure');
        $data = $request->validate([
            'documents' => ['present', 'array'], 'documents.*.code' => ['required', 'string', 'exists:document_types,code'],
            'documents.*.stage' => ['required', Rule::in(['application', 'onboarding'])], 'documents.*.required' => ['required', 'boolean'],
        ]);
        DB::transaction(function () use ($exercise, $data) {
            $exercise->documents()->delete();
            foreach ($data['documents'] as $d) {
                $exercise->documents()->create(['document_type_id' => DocumentType::where('code', $d['code'])->value('id'), 'stage' => $d['stage'], 'required' => $d['required']]);
            }
        });

        return new ExerciseResource($exercise->load(self::DETAIL));
    }

    private function validated(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'academic_session_id' => [$req, 'integer', 'exists:academic_sessions,id'],
            'category' => [$req, 'string', Rule::exists('admission_categories', 'code')->where('active', true)],
            'name' => [$req, 'string', 'max:255'],
            'opens_at' => [$req, 'date'], 'closes_at' => [$req, 'date', 'after:opens_at'],
            'application_fee' => ['sometimes', 'numeric', 'min:0', 'max:10000000'], 'acceptance_fee' => ['sometimes', 'numeric', 'min:0', 'max:10000000'],
            'capacity' => ['nullable', 'integer', 'min:1'], 'acceptance_days' => ['sometimes', 'integer', 'between:1,180'],
            'instructions' => ['nullable', 'string', 'max:10000'],
        ]);
        if (isset($data['category'])) {
            $data['admission_category_id'] = AdmissionCategory::where('code', $data['category'])->value('id');
            unset($data['category']);
        }

        return $data;
    }

    private function ruleValidation(): array
    {
        return [
            'rules' => ['sometimes', 'array'],
            'rules.min_utme_score' => ['nullable', 'integer', 'between:0,400'],
            'rules.required_utme_subjects' => ['nullable', 'array', 'max:4'], 'rules.required_utme_subjects.*' => ['string', 'exists:olevel_subjects,code'],
            'rules.required_credit_subjects' => ['nullable', 'array', 'max:9'], 'rules.required_credit_subjects.*' => ['string', 'exists:olevel_subjects,code'],
            'rules.min_credit_passes' => ['nullable', 'integer', 'between:0,9'], 'rules.max_sittings' => ['nullable', 'integer', 'between:1,3'],
            'rules.require_verified_documents' => ['nullable', 'boolean'], 'rules.manual_review' => ['nullable', 'boolean'],
        ];
    }

    private function rules(array $data): array
    {
        return array_filter(array_intersect_key($data['rules'] ?? [], array_flip(ScreeningEngine::RULE_KEYS)), fn ($v) => $v !== null && $v !== []);
    }
}
