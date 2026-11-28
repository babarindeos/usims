<?php

namespace App\Modules\Admissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admissions\Http\Resources\ApplicationResource;
use App\Modules\Admissions\Http\Resources\ExerciseResource;
use App\Modules\Admissions\Models\AdmissionExercise;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\ApplicationDocument;
use App\Modules\Admissions\Models\ClearanceItem;
use App\Modules\Admissions\Models\DocumentType;
use App\Modules\Admissions\Services\ApplicantAccountService;
use App\Modules\Admissions\Services\ApplicationService;
use App\Modules\Admissions\Services\ClearanceService;
use App\Modules\Admissions\Services\OfferService;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The applicant portal (PRD 5.3 to 6.3). Applicants only ever reach their own applications. */
class ApplicantController extends Controller
{
    private const DETAIL = [
        'person', 'exercise.category', 'exercise.session', 'choice.programme.orgUnit', 'secondChoice.programme.orgUnit',
        'sittings.examBody', 'sittings.results.subject', 'sittings.results.grade', 'education', 'currentDocuments.documentType',
        'payments.evidence', 'history.user', 'offer.programme', 'offer.clearance.unit', 'offer.clearance.orgUnit', 'offer.clearance.actions.user',
    ];

    public function __construct(private ApplicationService $applications, private OfferService $offers, private ClearanceService $clearance) {}

    public function register(Request $request, ApplicantAccountService $accounts): JsonResponse
    {
        $data = $request->validate([
            'surname' => ['required', 'string', 'max:100'], 'first_name' => ['required', 'string', 'max:100'], 'other_names' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ], ['email.unique' => 'An account already uses this email. Sign in, or reset your password.']);
        $accounts->register($data);

        return response()->json(['message' => 'Account created. Check your email for a link to verify your address, then sign in.'], 201);
    }

    public function verify(Request $request, string $user, string $hash, ApplicantAccountService $accounts): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'This verification link has expired or is not valid.');
        $accounts->verify(User::where('uuid', $user)->firstOrFail(), $hash);

        return redirect('/login?verified=1');
    }

    public function resendVerification(Request $request, ApplicantAccountService $accounts): JsonResponse
    {
        $accounts->sendVerification($request->user());

        return response()->json(['message' => 'If your email is not yet verified, a new link is on its way.']);
    }

    /** Public: only exercises taking applications right now are shown (PRD scenario 1). */
    public function openExercises(): AnonymousResourceCollection
    {
        $open = AdmissionExercise::with(['session', 'category', 'programmes' => fn ($q) => $q->where('active', true)->with('programme.orgUnit'), 'documents.documentType'])
            ->whereIn('status', ['open', 'scheduled'])->where('opens_at', '<=', now())->where('closes_at', '>', now())
            ->orderBy('closes_at')->get();

        return ExerciseResource::collection($open);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('applications.apply');

        return ApplicationResource::collection(Application::with(['person', 'exercise.category', 'exercise.session', 'choice.programme.orgUnit', 'secondChoice.programme.orgUnit', 'offer.programme'])
            ->where('person_id', $request->user()->person_id)->orderByDesc('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('applications.apply');
        if (! $request->user()->email_verified_at) {
            throw ValidationException::withMessages(['email' => 'Verify your email address before starting an application.']);
        }
        $data = $request->validate(['exercise_id' => ['required', 'string', 'exists:admission_exercises,uuid']]);
        $application = $this->applications->start($request->user(), AdmissionExercise::where('uuid', $data['exercise_id'])->firstOrFail());
        $this->applications->ensurePayment($application, 'application_fee', (float) $application->exercise->application_fee);

        return $this->present($application)->response()->setStatusCode(201);
    }

    public function show(Request $request, Application $application): ApplicationResource
    {
        $this->own($request, $application);

        return $this->present($application);
    }

    public function biodata(Request $request, Application $application): ApplicationResource
    {
        $this->own($request, $application);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:20'], 'surname' => ['required', 'string', 'max:100'], 'first_name' => ['required', 'string', 'max:100'],
            'other_names' => ['nullable', 'string', 'max:150'], 'date_of_birth' => ['required', 'date', 'before:-10 years'],
            'gender' => ['required', Rule::in(['male', 'female'])], 'nationality' => ['required', 'string', 'max:100'],
            'state_of_origin' => ['required', 'string', 'max:100'], 'lga' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', Rule::unique('persons', 'phone')->ignore($application->person_id)->whereNull('merged_into_person_id')],
            'address' => ['required', 'string', 'max:1000'],
        ], ['phone.unique' => 'Another record already uses this phone number.']);
        $this->applications->updateBiodata($application, $data);

        return $this->present($application);
    }

    public function details(Request $request, Application $application): ApplicationResource
    {
        $this->own($request, $application);
        $data = $request->validate([
            'exercise_programme_id' => ['sometimes', 'nullable', 'integer'], 'second_choice_id' => ['sometimes', 'nullable', 'integer'],
            'next_of_kin' => ['sometimes', 'array'], 'next_of_kin.name' => ['required_with:next_of_kin', 'string', 'max:200'],
            'next_of_kin.relationship' => ['required_with:next_of_kin', 'string', 'max:60'], 'next_of_kin.phone' => ['required_with:next_of_kin', 'string', 'max:30'],
            'next_of_kin.address' => ['nullable', 'string', 'max:1000'],
            'jamb_reg_no' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'], 'jamb_year' => ['sometimes', 'nullable', 'integer', 'between:2000,2100'],
            'utme_score' => ['sometimes', 'nullable', 'integer', 'between:0,400'],
            'utme_subjects' => ['sometimes', 'nullable', 'array', 'max:4'], 'utme_subjects.*' => ['string', 'exists:olevel_subjects,code', 'distinct'],
        ]);
        if (isset($data['jamb_reg_no'])) {
            $data['jamb_reg_no'] = strtoupper($data['jamb_reg_no']);
        }
        $this->applications->updateDetails($application, $data);

        return $this->present($application);
    }

    public function olevel(Request $request, Application $application): ApplicationResource
    {
        $this->own($request, $application);
        $data = $request->validate([
            'sittings' => ['present', 'array', 'max:3'],
            'sittings.*.exam_body' => ['required', 'string'], 'sittings.*.exam_year' => ['required', 'integer', 'between:1980,2100'],
            'sittings.*.exam_number' => ['required', 'string', 'max:30'],
            'sittings.*.results' => ['required', 'array', 'min:1', 'max:12'],
            'sittings.*.results.*.subject' => ['required', 'string'], 'sittings.*.results.*.grade' => ['required', 'string', 'max:5'],
        ]);
        $this->applications->replaceSittings($application, $data['sittings']);

        return $this->present($application);
    }

    public function education(Request $request, Application $application): ApplicationResource
    {
        $this->own($request, $application);
        $data = $request->validate([
            'records' => ['present', 'array', 'max:10'], 'records.*.institution' => ['required', 'string', 'max:255'],
            'records.*.qualification' => ['required', 'string', 'max:100'], 'records.*.course_of_study' => ['nullable', 'string', 'max:255'],
            'records.*.grade' => ['nullable', 'string', 'max:50'], 'records.*.year_obtained' => ['required', 'integer', 'between:1960,2100'],
        ]);
        $this->applications->replaceEducation($application, $data['records']);

        return $this->present($application);
    }

    public function upload(Request $request, Application $application): JsonResponse
    {
        $this->own($request, $application);
        $data = $request->validate([
            'document_type' => ['required', 'string', Rule::exists('document_types', 'code')->where('active', true)],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
        $this->applications->upload($application, DocumentType::where('code', $data['document_type'])->first(), $request->file('file'));

        return $this->present($application)->response()->setStatusCode(201);
    }

    public function pay(Request $request, Application $application, string $purpose): ApplicationResource
    {
        $this->own($request, $application);
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:60'], 'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'evidence_document_id' => ['required', 'string', 'exists:application_documents,uuid'],
        ]);
        $payment = $application->payments()->where('purpose', $purpose)->firstOrFail();
        $evidence = ApplicationDocument::where('uuid', $data['evidence_document_id'])->first();
        $this->applications->recordPayment($payment, $data, $evidence);

        return $this->present($application);
    }

    public function submit(Request $request, Application $application): ApplicationResource
    {
        $this->own($request, $application);
        $this->applications->submit($application, $request->user());

        return $this->present($application->refresh());
    }

    public function accept(Request $request, Application $application): ApplicationResource
    {
        $this->own($request, $application);
        $request->validate(['declaration' => ['accepted']], ['declaration.accepted' => 'Confirm the acceptance declaration.']);
        $this->offers->accept($application->offer ?? abort(404), $request->user());

        return $this->present($application);
    }

    public function decline(Request $request, Application $application): ApplicationResource
    {
        $this->own($request, $application);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->offers->decline($application->offer ?? abort(404), $request->user(), $data['reason']);

        return $this->present($application);
    }

    public function onboarding(Request $request, Application $application): ApplicationResource
    {
        $this->own($request, $application);
        $data = $request->validate([
            'sponsor' => ['required', 'array'], 'sponsor.name' => ['required', 'string', 'max:200'], 'sponsor.relationship' => ['required', 'string', 'max:60'],
            'sponsor.phone' => ['required', 'string', 'max:30'], 'sponsor.address' => ['nullable', 'string', 'max:1000'],
            'residence' => ['required', 'array'], 'residence.type' => ['required', Rule::in(['on_campus', 'off_campus'])], 'residence.address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact' => ['required', 'array'], 'emergency_contact.name' => ['required', 'string', 'max:200'], 'emergency_contact.phone' => ['required', 'string', 'max:30'],
            'medical' => ['nullable', 'array'], 'medical.blood_group' => ['nullable', 'string', 'max:5'], 'medical.genotype' => ['nullable', 'string', 'max:5'],
            'medical.conditions' => ['nullable', 'string', 'max:2000'], 'medical.disability' => ['nullable', 'string', 'max:500'],
            'declaration' => ['accepted'],
        ]);
        $this->offers->submitOnboarding($application->offer ?? abort(404), $data);

        return $this->present($application);
    }

    public function resubmitClearance(Request $request, Application $application, ClearanceItem $item): ApplicationResource
    {
        $this->own($request, $application);
        abort_unless($item->admission_offer_id === $application->offer?->id, 404);
        $this->clearance->resubmit($item, $request->user(), $request->validate(['remarks' => ['nullable', 'string', 'max:1000']])['remarks'] ?? null);

        return $this->present($application);
    }

    private function own(Request $request, Application $application): void
    {
        Gate::authorize('applications.apply');
        abort_unless($application->person_id === $request->user()->person_id, 404);
    }

    private function present(Application $application): ApplicationResource
    {
        $application->load(self::DETAIL);

        return (new ApplicationResource($application))->withMissing($application->status === 'draft' || $application->corrections_open ? $this->applications->missing($application) : []);
    }
}
