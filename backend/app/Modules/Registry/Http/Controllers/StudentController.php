<?php

namespace App\Modules\Registry\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admissions\Models\AdmissionOffer;
use App\Modules\Identity\Services\AccessService;
use App\Modules\Programmes\Models\Programme;
use App\Modules\Registry\Http\Resources\StudentResource;
use App\Modules\Registry\Models\Student;
use App\Modules\Registry\Services\MatriculationService;
use App\Modules\Registry\Services\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    private const DETAIL = ['person', 'programme.orgUnit.parent', 'category', 'admissionSession', 'curriculum', 'offer', 'statusHistory.user'];

    public function __construct(private AccessService $access) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $term = trim((string) $request->query('q'));
        $query = Student::query()->with(['person', 'programme.orgUnit', 'category', 'admissionSession'])
            ->when($request->query('programme'), fn ($q, $p) => $q->whereHas('programme', fn ($w) => $w->where('uuid', $p)))
            ->when($request->query('level'), fn ($q, $l) => $q->where('current_level', (int) $l))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('session'), fn ($q, $s) => $q->where('admission_session_id', (int) $s))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('matric_no', strtoupper($term))->orWhere('student_no', strtoupper($term))
                ->orWhereHas('person', fn ($p) => $p->whereRaw("lower(surname || ' ' || first_name) like ?", ['%'.mb_strtolower($term).'%']))));
        $programmes = $this->access->restrictToOrgUnits($request->user(), 'students.view', Programme::query()->select('programmes.id'), 'org_unit_id');

        return StudentResource::collection($query->whereIn('programme_id', $programmes)->orderBy('matric_no')->paginate(min((int) $request->query('per_page', 50), 200)));
    }

    public function show(Request $request, Student $student): StudentResource
    {
        Gate::authorize('students.view', $student);
        DB::table('access_logs')->insert([
            'user_id' => $request->user()->id, 'resource_type' => $student->getMorphClass(), 'resource_id' => $student->id,
            'purpose' => 'view', 'ip_address' => $request->ip(), 'created_at' => now(),
        ]);

        return new StudentResource($student->load(self::DETAIL));
    }

    public function changeStatus(Request $request, Student $student, StudentService $students): StudentResource
    {
        Gate::authorize('students.manage', $student);
        $data = $request->validate([
            'status' => ['required', Rule::in(Student::STATUSES)], 'reason' => ['required', 'string', 'max:1000'],
            'effective_on' => ['nullable', 'date'],
        ]);
        $students->changeStatus($student, $data['status'], $data['reason'], $data['effective_on'] ?? null, $request->user());

        return new StudentResource($student->load(self::DETAIL));
    }

    public function matriculate(Request $request, MatriculationService $matriculation): JsonResponse
    {
        $data = $request->validate(['offers' => ['required', 'array', 'min:1', 'max:500'], 'offers.*' => ['string', 'exists:admission_offers,uuid']]);
        $done = [];
        $skipped = [];
        foreach (AdmissionOffer::whereIn('uuid', $data['offers'])->get() as $offer) {
            Gate::authorize('students.matriculate', $offer);
            if ($unmet = $matriculation->unmet($offer)) {
                $skipped[] = ['admission_no' => $offer->admission_no, 'unmet' => $unmet];

                continue;
            }
            $student = $matriculation->matriculate($offer, $request->user());
            $done[] = ['admission_no' => $offer->admission_no, 'matric_no' => $student->matric_no, 'student_id' => $student->uuid];
        }

        return response()->json(['data' => ['matriculated' => $done, 'skipped' => $skipped]]);
    }
}
