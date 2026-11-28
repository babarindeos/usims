<?php

namespace App\Modules\Admissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admissions\Http\Resources\ApplicationResource;
use App\Modules\Admissions\Models\AdmissionPayment;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\ApplicationDocument;
use App\Modules\Admissions\Models\ExerciseProgramme;
use App\Modules\Admissions\Services\ProcessingService;
use App\Modules\Identity\Services\AccessService;
use App\Modules\Programmes\Models\Programme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Registry's admission processing workspace (PRD 5.9). Departments and faculties see their own programmes. */
class ProcessingController extends Controller
{
    private const DETAIL = [
        'person', 'exercise.category', 'exercise.session', 'choice.programme.orgUnit', 'secondChoice.programme.orgUnit',
        'sittings.examBody', 'sittings.results.subject', 'sittings.results.grade', 'education', 'currentDocuments.documentType',
        'payments.evidence', 'history.user', 'screenings', 'offer.programme', 'offer.clearance.unit', 'offer.clearance.orgUnit',
    ];

    public function __construct(private ProcessingService $processing, private AccessService $access) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ApplicationResource::collection($this->query($request)->with(['person', 'exercise.category', 'exercise.session', 'choice.programme.orgUnit'])
            ->orderByDesc('utme_score')->orderBy('application_no')->paginate(min((int) $request->query('per_page', 50), 200)));
    }

    /** CSV of the filtered list, for committee papers and reports. */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->query($request)->with(['person', 'choice.programme'])->orderBy('application_no')->cursor();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Application no', 'Surname', 'First name', 'Gender', 'State', 'JAMB no', 'UTME score', 'Programme', 'Entry level', 'Screening', 'Status']);
            foreach ($rows as $a) {
                fputcsv($out, [$a->application_no, $a->person->surname, $a->person->first_name, $a->person->gender, $a->person->state_of_origin,
                    $a->jamb_reg_no, $a->utme_score, $a->choice?->programme->code, $a->choice?->entry_level, $a->screening_outcome, $a->status]);
            }
            fclose($out);
        }, 'applications-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function show(Application $application): ApplicationResource
    {
        Gate::authorize('admissions.view', $application);
        abort_if($application->status === 'draft', 404);
        DB::table('access_logs')->insert([
            'user_id' => auth()->id(), 'resource_type' => $application->getMorphClass(), 'resource_id' => $application->id,
            'purpose' => 'admission_processing', 'ip_address' => request()->ip(), 'created_at' => now(),
        ]);

        return new ApplicationResource($application->load(self::DETAIL));
    }

    public function act(Request $request, Application $application, string $action): ApplicationResource
    {
        $permission = $action === 'change-programme' ? 'admissions.change_programme' : 'admissions.process';
        Gate::authorize($permission, $application);
        $user = $request->user();
        $data = $request->validate([
            'reason' => [in_array($action, ['hold', 'reject', 'change-programme', 'open-corrections', 'close-corrections'], true) ? 'required' : 'nullable', 'string', 'max:1000'],
            'exercise_programme_id' => [$action === 'change-programme' ? 'required' : 'prohibited', 'integer'],
        ]);

        match ($action) {
            'screen' => $this->processing->rescreen($application, $user),
            'recommend' => $this->processing->recommend($application, $user, $data['reason'] ?? null),
            'hold' => $this->processing->hold($application, $user, $data['reason']),
            'reject' => $this->processing->reject($application, $user, $data['reason']),
            'open-corrections' => $this->processing->setCorrections($application, true, $user, $data['reason']),
            'close-corrections' => $this->processing->setCorrections($application, false, $user, $data['reason']),
            'change-programme' => $this->changeProgramme($application, $data, $request),
        };

        return new ApplicationResource($application->refresh()->load(self::DETAIL));
    }

    public function reviewDocument(Request $request, ApplicationDocument $document): ApplicationResource
    {
        Gate::authorize('admissions.process', $document->application);
        $data = $request->validate(['status' => ['required', Rule::in(['verified', 'rejected'])], 'remarks' => ['nullable', 'string', 'max:1000']]);
        $this->processing->reviewDocument($document, $request->user(), $data['status'], $data['remarks'] ?? null);

        return new ApplicationResource($document->application->load(self::DETAIL));
    }

    /** Applicants download their own files; staff download within their admissions scope. */
    public function download(Request $request, ApplicationDocument $document): StreamedResponse
    {
        $application = $document->application;
        $own = $application->person_id === $request->user()->person_id;
        abort_unless($own || $request->user()->can('admissions.view', $application) || $request->user()->can('admission_payments.verify'), 404);
        if (! $own) {
            DB::table('access_logs')->insert([
                'user_id' => $request->user()->id, 'resource_type' => $document->getMorphClass(), 'resource_id' => $document->id,
                'purpose' => 'document_view', 'ip_address' => $request->ip(), 'created_at' => now(),
            ]);
        }

        return Storage::disk('local')->response($document->path, $document->original_name, ['Content-Type' => $document->mime]);
    }

    /** Bursary's verification queue. */
    public function payments(Request $request): JsonResponse
    {
        Gate::authorize('admission_payments.verify');
        $rows = AdmissionPayment::with(['application.person', 'application.exercise', 'evidence'])
            ->where('status', $request->query('status', 'submitted'))->orderBy('submitted_at')->limit(500)->get();

        return response()->json(['data' => $rows->map(fn ($p) => [
            'id' => $p->uuid, 'purpose' => $p->purpose, 'amount' => (float) $p->amount, 'status' => $p->status, 'method' => $p->method,
            'reference' => $p->reference, 'paid_on' => $p->paid_on?->toDateString(), 'submitted_at' => $p->submitted_at?->toIso8601String(),
            'remarks' => $p->remarks, 'evidence_url' => $p->evidence ? "/api/v1/admission-documents/{$p->evidence->uuid}" : null,
            'applicant' => $p->application->person->fullName(), 'application_no' => $p->application->application_no ?? 'Not yet submitted',
            'exercise' => $p->application->exercise->name,
        ])]);
    }

    public function reviewPayment(Request $request, AdmissionPayment $payment): JsonResponse
    {
        Gate::authorize('admission_payments.verify');
        $data = $request->validate(['approve' => ['required', 'boolean'], 'remarks' => ['nullable', 'string', 'max:500']]);
        $this->processing->reviewPayment($payment, $request->user(), $data['approve'], $data['remarks'] ?? null);

        return response()->json(['data' => ['id' => $payment->uuid, 'status' => $payment->status]]);
    }

    private function changeProgramme(Application $application, array $data, Request $request): void
    {
        $choice = ExerciseProgramme::findOrFail($data['exercise_programme_id']);
        Gate::authorize('admissions.change_programme', $choice);
        $this->processing->changeProgramme($application, $choice, $request->user(), $data['reason']);
    }

    private function query(Request $request): Builder
    {
        Gate::authorize('admissions.view');
        $programmes = $this->access->restrictToOrgUnits($request->user(), 'admissions.view', Programme::query()->select('programmes.id'), 'org_unit_id');
        $term = trim((string) $request->query('q'));

        return Application::query()->whereNot('status', 'draft')
            ->whereIn('exercise_programme_id', ExerciseProgramme::query()->select('id')->whereIn('programme_id', $programmes))
            ->when($request->query('exercise'), fn ($q, $e) => $q->whereHas('exercise', fn ($w) => $w->where('uuid', $e)))
            ->when($request->query('programme'), fn ($q, $p) => $q->whereHas('choice.programme', fn ($w) => $w->where('uuid', $p)))
            ->when($request->query('department'), fn ($q, $d) => $q->whereHas('choice.programme.orgUnit', fn ($w) => $w->where('uuid', $d)))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('outcome'), fn ($q, $o) => $q->where('screening_outcome', $o))
            ->when($request->query('min_score'), fn ($q, $s) => $q->where('utme_score', '>=', (int) $s))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('application_no', strtoupper($term))->orWhere('jamb_reg_no', strtoupper($term))
                ->orWhereHas('person', fn ($p) => $p->whereRaw("lower(surname || ' ' || first_name) like ?", ['%'.mb_strtolower($term).'%']))));
    }
}
