<?php

namespace App\Modules\Admissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admissions\Models\AdmissionCategory;
use App\Modules\Admissions\Models\ClearanceUnit;
use App\Modules\Admissions\Models\DocumentType;
use App\Modules\Admissions\Models\ExamBody;
use App\Modules\Admissions\Models\OlevelSubject;
use App\Modules\Admissions\Services\OfferService;
use App\Modules\Institution\Models\OrgUnit;
use App\Modules\Institution\Models\Setting;
use App\Modules\Programmes\Models\ProgrammeCategory;
use App\Modules\Registry\Models\MatricFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Admission lists the institution maintains. Reads are open to signed-in users (forms need them). */
class AdmissionConfigController extends Controller
{
    public const KINDS = ['categories', 'exam-bodies', 'subjects', 'document-types', 'clearance-units', 'matric-formats'];

    public function index(Request $request, string $kind): JsonResponse
    {
        $all = $request->boolean('include_inactive');
        $rows = match ($kind) {
            'categories' => AdmissionCategory::query()->when(! $all, fn ($q) => $q->where('active', true))->orderBy('name')->get(),
            'exam-bodies' => ExamBody::with('grades')->when(! $all, fn ($q) => $q->where('active', true))->orderBy('code')->get(),
            'subjects' => OlevelSubject::query()->when(! $all, fn ($q) => $q->where('active', true))->orderBy('name')->get(),
            'document-types' => DocumentType::query()->when(! $all, fn ($q) => $q->where('active', true))->orderBy('name')->get(),
            'clearance-units' => ClearanceUnit::with('orgUnit')->when(! $all, fn ($q) => $q->where('active', true))->orderBy('sort_order')->get(),
            'matric-formats' => MatricFormat::query()->orderBy('id')->get(),
        };

        return response()->json(['data' => $rows->map(fn ($m) => $this->present($kind, $m))]);
    }

    public function store(Request $request, string $kind): JsonResponse
    {
        Gate::authorize('admissions.configure');
        $row = DB::transaction(fn () => $this->save($kind, $this->model($kind), $request));

        return response()->json(['data' => $this->present($kind, $row)], 201);
    }

    public function update(Request $request, string $kind, int $id): JsonResponse
    {
        Gate::authorize('admissions.configure');
        $row = $this->model($kind)->newQuery()->findOrFail($id);
        $row = DB::transaction(fn () => $this->save($kind, $row, $request));

        return response()->json(['data' => $this->present($kind, $row)]);
    }

    public function letterTemplate(): JsonResponse
    {
        Gate::authorize('admissions.configure');

        return response()->json(['data' => ['template' => Setting::where('key', 'admission_letter_template')->value('value') ?: OfferService::DEFAULT_LETTER,
            'placeholders' => ['institution', 'date', 'admission_no', 'application_no', 'name', 'session', 'programme', 'award', 'department', 'category', 'level', 'accept_by', 'acceptance_fee']]]);
    }

    public function updateLetterTemplate(Request $request): JsonResponse
    {
        Gate::authorize('admissions.configure');
        $data = $request->validate(['template' => ['required', 'string', 'max:20000']]);
        Setting::updateOrCreate(['key' => 'admission_letter_template'], ['value' => $data['template']]);

        return $this->letterTemplate();
    }

    private function model(string $kind): Model
    {
        return match ($kind) {
            'categories' => new AdmissionCategory, 'exam-bodies' => new ExamBody, 'subjects' => new OlevelSubject,
            'document-types' => new DocumentType, 'clearance-units' => new ClearanceUnit, 'matric-formats' => new MatricFormat,
        };
    }

    private function save(string $kind, Model $row, Request $request): Model
    {
        $creating = ! $row->exists;
        $req = $creating ? 'required' : 'sometimes';
        $code = fn (string $table) => $creating ? ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_]+$/', Rule::unique($table, 'code')] : ['prohibited'];
        $rules = match ($kind) {
            'categories' => ['code' => $code('admission_categories'), 'name' => [$req, 'string', 'max:120'], 'requires_jamb' => ['sometimes', 'boolean'], 'active' => ['sometimes', 'boolean']],
            'subjects' => ['code' => $code('olevel_subjects'), 'name' => [$req, 'string', 'max:120'], 'active' => ['sometimes', 'boolean']],
            'document-types' => ['code' => $code('document_types'), 'name' => [$req, 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:1000'],
                'needs_verification' => ['sometimes', 'boolean'], 'max_kb' => ['sometimes', 'integer', 'between:50,20480'], 'active' => ['sometimes', 'boolean']],
            'exam-bodies' => ['code' => $code('exam_bodies'), 'name' => [$req, 'string', 'max:160'], 'active' => ['sometimes', 'boolean'],
                'grades' => [$creating ? 'required' : 'sometimes', 'array', 'min:2'], 'grades.*.grade' => ['required', 'string', 'max:5', 'distinct'],
                'grades.*.points' => ['required', 'integer', 'between:0,100'], 'grades.*.is_credit' => ['required', 'boolean']],
            'clearance-units' => ['code' => $code('clearance_units'), 'name' => [$req, 'string', 'max:120'], 'target' => [$req, Rule::in(ClearanceUnit::TARGETS)],
                'org_unit_id' => ['nullable', 'string', 'exists:org_units,uuid'], 'instructions' => ['nullable', 'string', 'max:2000'],
                'sort_order' => ['sometimes', 'integer', 'min:0'], 'active' => ['sometimes', 'boolean']],
            'matric-formats' => ['name' => [$req, 'string', 'max:120'], 'format' => [$req, 'string', 'max:100', 'regex:/\{SEQ:[1-9]\}/'],
                'admission_category' => ['nullable', 'string', 'exists:admission_categories,code'], 'programme_category' => ['nullable', 'string', 'exists:programme_categories,code'],
                'active' => ['sometimes', 'boolean']],
        };
        $data = $request->validate($rules, ['format.regex' => 'The format needs a sequence token such as {SEQ:4}.']);

        if ($kind === 'clearance-units' && array_key_exists('org_unit_id', $data)) {
            $data['org_unit_id'] = $data['org_unit_id'] ? OrgUnit::where('uuid', $data['org_unit_id'])->value('id') : null;
        }
        if ($kind === 'matric-formats') {
            foreach (['admission_category' => [AdmissionCategory::class, 'admission_category_id'], 'programme_category' => [ProgrammeCategory::class, 'programme_category_id']] as $key => [$class, $column]) {
                if (array_key_exists($key, $data)) {
                    $data[$column] = $data[$key] ? $class::where('code', $data[$key])->value('id') : null;
                    unset($data[$key]);
                }
            }
        }
        $grades = $data['grades'] ?? null;
        unset($data['grades']);
        $row->fill($data)->save();

        if ($kind === 'exam-bodies' && $grades !== null) {
            // Grades already used in results are kept; others are replaced by the submitted scale.
            foreach ($grades as $i => $g) {
                $row->grades()->updateOrCreate(['grade' => strtoupper($g['grade'])], ['points' => $g['points'], 'is_credit' => $g['is_credit'], 'sort_order' => $i]);
            }
            $row->grades()->whereNotIn('grade', array_map(fn ($g) => strtoupper($g['grade']), $grades))
                ->whereNotIn('id', DB::table('olevel_results')->select('exam_grade_id'))->delete();
        }

        return $row->refresh();
    }

    private function present(string $kind, Model $m): array
    {
        return match ($kind) {
            'exam-bodies' => $m->only(['id', 'code', 'name', 'active']) + ['grades' => $m->grades->map->only(['grade', 'points', 'is_credit'])],
            'clearance-units' => $m->only(['id', 'code', 'name', 'target', 'instructions', 'sort_order', 'active']) + ['org_unit' => $m->orgUnit ? ['id' => $m->orgUnit->uuid, 'name' => $m->orgUnit->name] : null],
            'matric-formats' => $m->only(['id', 'name', 'format', 'active']) + [
                'admission_category' => $m->admission_category_id ? AdmissionCategory::find($m->admission_category_id)?->code : null,
                'programme_category' => $m->programme_category_id ? ProgrammeCategory::find($m->programme_category_id)?->code : null,
            ],
            default => $m->only(['id', 'code', 'name', 'active', 'requires_jamb', 'description', 'needs_verification', 'max_kb']),
        };
    }
}
