<?php

namespace App\Http\Controllers;

use App\Models\Pipeline;
use App\Support\BusinessUnitResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PipelineController extends Controller
{
    public function index()
    {
        return view('pipelines.index', ['pipelines' => Pipeline::with(['stages', 'businessUnit'])->withCount('opportunities')->latest()->get()]);
    }

    public function create(BusinessUnitResolver $businessUnits) { return view('pipelines.form', ['pipeline' => new Pipeline, 'businessUnits' => $businessUnits->options()]); }

    public function store(Request $request, BusinessUnitResolver $businessUnits)
    {
        $request->validate(['business_type_custom' => ['nullable', 'string', 'max:120']]);
        $this->resolveBusinessType($request, $businessUnits);
        $data = $request->validate(['name' => ['required'], 'description' => ['nullable'], 'business_unit_id' => ['nullable', 'exists:business_units,id'], 'business_type' => ['nullable', 'string', 'max:120'], 'counts_as_custom_noo' => ['nullable', 'boolean']]);
        $data['counts_as_custom_noo'] = $request->boolean('counts_as_custom_noo');
        $pipeline = Pipeline::create($data + ['slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)), 'created_by' => $request->user()->id]);
        foreach (['New Lead', 'Qualified', 'Need Analysis', 'Quotation', 'Negotiation', 'Closed Won', 'Closed Lost'] as $i => $name) {
            $pipeline->stages()->create(['name' => $name, 'slug' => Str::slug($name), 'position' => $i + 1, 'probability' => [10, 25, 40, 60, 80, 100, 0][$i], 'color' => ['#64748b', '#3b82f6', '#8b5cf6', '#f59e0b', '#f97316', '#10b981', '#ef4444'][$i], 'is_won' => $name === 'Closed Won', 'is_lost' => $name === 'Closed Lost']);
        }
        return redirect()->route('pipelines.edit', $pipeline)->with('success', 'Pipeline dibuat. Atur stage dan rule di bawah.');
    }

    public function edit(Pipeline $pipeline, BusinessUnitResolver $businessUnits)
    {
        $pipeline->load('stages.rules');
        return view('pipelines.form', ['pipeline' => $pipeline, 'businessUnits' => $businessUnits->options()]);
    }

    public function update(Request $request, Pipeline $pipeline, BusinessUnitResolver $businessUnits)
    {
        $request->validate(['business_type_custom' => ['nullable', 'string', 'max:120']]);
        $this->resolveBusinessType($request, $businessUnits);
        $data = $request->validate([
            'name' => ['required'], 'description' => ['nullable'], 'business_unit_id' => ['nullable', 'exists:business_units,id'], 'business_type' => ['nullable', 'string', 'max:120'],
            'counts_as_custom_noo' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'], 'stages' => ['required', 'array', 'min:1'],
            'stages.*.id' => ['nullable', 'exists:pipeline_stages,id'], 'stages.*.name' => ['required'],
            'stages.*.color' => ['required'], 'stages.*.probability' => ['required', 'integer', 'between:0,100'],
            'stages.*.sla_days' => ['nullable', 'integer', 'min:0'], 'stages.*.is_won' => ['nullable', 'boolean'],
            'stages.*.is_lost' => ['nullable', 'boolean'], 'stages.*.rules_text' => ['nullable'],
        ]);

        $data['counts_as_custom_noo'] = $request->boolean('counts_as_custom_noo');
        DB::transaction(function () use ($pipeline, $data) {
            $pipeline->update(collect($data)->except('stages')->all());
            $kept = [];
            foreach ($data['stages'] as $position => $stageData) {
                $stage = $pipeline->stages()->updateOrCreate(
                    ['id' => $stageData['id'] ?? null],
                    ['name' => $stageData['name'], 'slug' => Str::slug($stageData['name']), 'position' => $position + 1, 'color' => $stageData['color'], 'probability' => $stageData['probability'], 'sla_days' => $stageData['sla_days'] ?? null, 'is_won' => (bool) ($stageData['is_won'] ?? false), 'is_lost' => (bool) ($stageData['is_lost'] ?? false), 'is_active' => true]
                );
                $kept[] = $stage->id;
                $stage->rules()->delete();
                foreach (preg_split('/\r\n|\r|\n/', $stageData['rules_text'] ?? '') as $line) {
                    if (! trim($line)) continue;
                    [$type, $field, $label] = array_pad(array_map('trim', explode('|', $line, 3)), 3, null);
                    $stage->rules()->create(['rule_type' => in_array($type, ['field', 'note', 'file', 'task', 'approval', 'follow_up', 'reason']) ? $type : 'field', 'field_key' => $field ?: null, 'label' => $label ?: ($field ?: $type), 'is_mandatory' => true]);
                }
            }
            $pipeline->stages()->whereNotIn('id', $kept)->whereDoesntHave('opportunities')->delete();
        });

        return back()->with('success', 'Pipeline dan stage rules diperbarui.');
    }

    private function resolveBusinessType(Request $request, BusinessUnitResolver $businessUnits): void
    {
        $businessUnit = $businessUnits->resolve($request->input('business_type'), $request->input('business_type_custom'), $request->user()->isMasterAdmin());
        $request->merge([
            'business_type' => $businessUnit?->name,
            'business_unit_id' => $businessUnit?->id,
        ]);
    }
}
