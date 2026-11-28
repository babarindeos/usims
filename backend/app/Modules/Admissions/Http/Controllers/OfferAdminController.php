<?php

namespace App\Modules\Admissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admissions\Http\Resources\OfferResource;
use App\Modules\Admissions\Models\AdmissionOffer;
use App\Modules\Admissions\Models\ClearanceItem;
use App\Modules\Admissions\Services\ClearanceService;
use App\Modules\Identity\Services\AccessService;
use App\Modules\Programmes\Models\Programme;
use App\Modules\Registry\Models\Student;
use App\Modules\Registry\Services\MatriculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Offers after issue: clearance by each unit and matriculation readiness (PRD 6.3 to 6.5). */
class OfferAdminController extends Controller
{
    public function __construct(private ClearanceService $clearance, private MatriculationService $matriculation, private AccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('admissions.view');
        $programmes = $this->access->restrictToOrgUnits($request->user(), 'admissions.view', Programme::query()->select('programmes.id'), 'org_unit_id');
        $rows = AdmissionOffer::with(['programme', 'application.person', 'application.payments', 'clearance.unit'])
            ->whereIn('programme_id', $programmes)
            ->when($request->query('exercise'), fn ($q, $e) => $q->whereHas('application.exercise', fn ($w) => $w->where('uuid', $e)))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('admission_no')->limit(1000)->get();
        $students = Student::whereIn('admission_offer_id', $rows->pluck('id'))->pluck('matric_no', 'admission_offer_id');

        return response()->json(['data' => $rows->map(fn ($o) => [
            'id' => $o->uuid, 'admission_no' => $o->admission_no, 'name' => $o->application->person->fullName(),
            'application_no' => $o->application->application_no, 'programme' => $o->programme->code, 'entry_level' => $o->entry_level,
            'status' => $o->status, 'accept_by' => $o->accept_by->toDateString(),
            'clearance' => $o->clearance->map(fn ($c) => ['unit' => $c->unit->name, 'status' => $c->status]),
            'matric_no' => $students[$o->id] ?? null,
            'unmet' => isset($students[$o->id]) ? [] : $this->matriculation->unmet($o),
        ])]);
    }

    public function show(AdmissionOffer $offer): OfferResource
    {
        Gate::authorize('admissions.view', $offer);
        $offer->load(['programme', 'clearance.unit', 'clearance.orgUnit', 'clearance.actions.user']);
        $student = Student::where('admission_offer_id', $offer->id)->first();

        return (new OfferResource($offer))->withExtra([
            'unmet' => $student ? [] : $this->matriculation->unmet($offer),
            'student' => $student ? ['id' => $student->uuid, 'matric_no' => $student->matric_no, 'student_no' => $student->student_no] : null,
        ]);
    }

    /** Items for the units the officer clears, oldest first. */
    public function clearanceQueue(Request $request): JsonResponse
    {
        Gate::authorize('clearance.review');
        $query = ClearanceItem::with(['unit', 'orgUnit', 'offer.programme', 'offer.application.person', 'offer.application.currentDocuments.documentType'])
            ->where('status', $request->query('status', 'submitted'));
        $rows = $this->access->restrictToOrgUnits($request->user(), 'clearance.review', $query, 'clearance_items.org_unit_id')
            ->orderBy('updated_at')->limit(500)->get();

        return response()->json(['data' => $rows->map(fn ($c) => [
            'id' => $c->id, 'unit' => $c->unit->name, 'instructions' => $c->unit->instructions, 'reviewer_unit' => $c->orgUnit?->name, 'status' => $c->status,
            'admission_no' => $c->offer->admission_no, 'name' => $c->offer->application->person->fullName(), 'programme' => $c->offer->programme->code,
            'offer_id' => $c->offer->uuid, 'onboarding' => $c->offer->onboarding,
            'documents' => $c->offer->application->currentDocuments->map(fn ($d) => ['name' => $d->documentType->name, 'status' => $d->status, 'url' => "/api/v1/admission-documents/{$d->uuid}"]),
            'updated_at' => $c->updated_at->toIso8601String(),
        ])]);
    }

    public function reviewClearance(Request $request, ClearanceItem $item): JsonResponse
    {
        Gate::authorize('clearance.review', $item);
        $data = $request->validate(['status' => ['required', Rule::in(['under_review', 'approved', 'rejected', 'requires_correction'])], 'remarks' => ['nullable', 'string', 'max:1000']]);
        $this->clearance->review($item, $request->user(), $data['status'], $data['remarks'] ?? null);

        return response()->json(['data' => ['id' => $item->id, 'status' => $item->status]]);
    }
}
