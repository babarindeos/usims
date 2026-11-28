<?php

namespace App\Modules\Programmes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Programmes\Models\AwardType;
use App\Modules\Programmes\Models\ProgrammeCategory;
use App\Modules\Programmes\Models\StudyMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Award types, study modes and programme categories: small institution-defined lists. */
class AcademicConfigController extends Controller
{
    public const KINDS = ['award-types', 'study-modes', 'programme-categories'];

    private const MODELS = [
        'award-types' => AwardType::class,
        'study-modes' => StudyMode::class,
        'programme-categories' => ProgrammeCategory::class,
    ];

    public function index(Request $request, string $kind): JsonResponse
    {
        $rows = (self::MODELS[$kind])::query()
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('active', true))
            ->orderBy('name')->get();

        return response()->json(['data' => $rows->map(fn (Model $m) => $this->present($m))]);
    }

    public function store(Request $request, string $kind): JsonResponse
    {
        Gate::authorize('academic_config.manage');
        $class = self::MODELS[$kind];
        $data = $request->validate($this->rules($kind, $class, null));
        $row = $class::create($data);

        return response()->json(['data' => $this->present($row)], 201);
    }

    public function update(Request $request, string $kind, string $code): JsonResponse
    {
        Gate::authorize('academic_config.manage');
        $class = self::MODELS[$kind];
        $row = $class::where('code', $code)->firstOrFail();
        // Codes are referenced by programmes and stay fixed; deactivate and add a new entry instead.
        $row->update($request->validate($this->rules($kind, $class, $row)));

        return response()->json(['data' => $this->present($row)]);
    }

    private function rules(string $kind, string $class, ?Model $row): array
    {
        $table = (new $class)->getTable();
        $rules = [
            'name' => [$row ? 'sometimes' : 'required', 'string', 'max:120'],
            'active' => ['sometimes', 'boolean'],
        ];
        if (! $row) {
            $rules['code'] = ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/', Rule::unique($table, 'code')];
        }
        if ($kind === 'award-types') {
            $rules['level'] = [$row ? 'sometimes' : 'required', Rule::in(['sub_degree', 'undergraduate', 'postgraduate', 'certificate'])];
        }

        return $rules;
    }

    private function present(Model $m): array
    {
        return array_filter([
            'code' => $m->code, 'name' => $m->name, 'active' => (bool) $m->active, 'level' => $m->level ?? null,
        ], fn ($v) => $v !== null);
    }
}
