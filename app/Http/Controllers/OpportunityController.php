<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Product;
use App\Models\User;
use App\Services\CrmNotifier;
use App\Services\StageTransitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OpportunityController extends Controller
{
    public function index(Request $request)
    {
        $opportunities = Opportunity::query()->visibleTo($request->user())->with([
            'customer', 'stage', 'owner', 'pipeline',
            'items' => fn ($query) => $query->select('id', 'opportunity_id', 'product_name')->orderBy('id'),
        ])
            ->when($request->customer, fn ($q, $id) => $q->where('customer_id', $id))
            ->when($request->pipeline, fn ($q, $id) => $q->where('pipeline_id', $id))
            ->when($request->stage, fn ($q, $id) => $q->where('pipeline_stage_id', $id))
            ->when($request->owner, fn ($q, $id) => $q->where('owner_id', $id))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('title', 'like', "%$s%")->orWhere('opportunity_id', 'like', "%$s%")->orWhereHas('customer', fn ($q) => $q->where('company_name', 'like', "%$s%"))))
            ->when($request->sort === 'value', fn ($q) => $q->orderByDesc('estimated_value'))
            ->when($request->sort === 'close', fn ($q) => $q->orderByRaw('expected_close_date IS NULL')->orderBy('expected_close_date'))
            ->when(! in_array($request->sort, ['value', 'close'], true), fn ($q) => $q->latest())
            ->paginate(20)->withQueryString();

        return view('opportunities.index', [
            'opportunities' => $opportunities,
            'pipelines' => Pipeline::where('is_active', true)->get(),
            'stages' => PipelineStage::where('is_active', true)->with('pipeline')->orderBy('pipeline_id')->orderBy('position')->get(),
            'owners' => User::where('is_active', true)->whereHas('roles', fn ($q) => $q->whereIn('slug', ['sales', 'csa']))->orderBy('name')->get(),
        ]);
    }

    public function kanban(Request $request)
    {
        $pipeline = Pipeline::with('stages')->findOrFail($request->pipeline ?? Pipeline::where('is_active', true)->value('id'));
        $opportunities = Opportunity::visibleTo($request->user())
            ->with(['customer', 'owner', 'tasks', 'items'])
            ->where('pipeline_id', $pipeline->id)
            ->orderByDesc('stage_entered_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('pipeline_stage_id');
        $pipelines = Pipeline::where('is_active', true)->orderBy('name')->get();
        return view('opportunities.kanban', compact('pipeline', 'pipelines', 'opportunities'));
    }

    public function create(Request $request)
    {
        $opportunity = new Opportunity(['customer_id' => $request->customer]);

        if ($request->boolean('from_initial_need') && $request->customer) {
            $customer = Customer::visibleTo($request->user())->findOrFail($request->customer);
            $interestItems = collect($customer->interestItems());
            $opportunity->fill([
                'title' => $customer->product_interest
                    ? $customer->product_interest.' untuk '.$customer->company_name
                    : 'Kebutuhan awal '.$customer->company_name,
                'product_name' => $customer->product_interest,
                'estimated_quantity' => $customer->estimated_need,
                'quantity_unit' => $customer->estimated_need_unit ?: 'pcs',
            ]);
            $opportunity->setAttribute('initial_items', $interestItems->map(fn ($item) => [
                'product_id' => null,
                'product_name' => $item['product_name'] ?? '',
                'quantity' => max(1, (int) ($item['estimated_need'] ?? 1)),
                'quantity_unit' => $item['estimated_need_unit'] ?? 'pcs',
                'target_price' => null,
            ])->values()->all());
        }

        return view('opportunities.form', $this->formData($opportunity));
    }

    public function store(Request $request, CrmNotifier $notifier)
    {
        if (! $request->has('items')) {
            $quantity = max(1, (int) preg_replace('/\D/', '', (string) $request->input('estimated_quantity', 1)));
            $estimatedValue = (float) preg_replace('/\D/', '', (string) $request->input('estimated_value', 0));
            $request->merge(['items' => [[
                'product_id' => $request->input('product_id'),
                'product_name' => $request->input('product_name') ?: 'Produk belum ditentukan',
                'quantity' => $quantity,
                'quantity_unit' => $request->input('quantity_unit', 'pcs'),
                'target_price' => $request->input('target_price'),
                'photo' => $request->file('photo'),
                'unit_price' => $request->filled('offered_price')
                    ? $request->input('offered_price')
                    : ($quantity ? $estimatedValue / $quantity : 0),
            ]]]);
        }

        $data = $this->validated($request);
        $items = collect($data['items']);
        $participantIds = collect($data['participant_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $request->user()->id)
            ->unique()
            ->values();
        unset($data['items'], $data['participant_ids']);
        $customer = Customer::visibleTo($request->user())->findOrFail($data['customer_id']);

        if ($this->canAssignOwner($request->user())) {
            $data['owner_id'] ??= $customer->sales_owner_id ?: $request->user()->id;
            abort_unless(
                User::whereKey($data['owner_id'])->where('is_active', true)
                    ->whereHas('roles', fn ($query) => $query->where('slug', 'sales'))->exists()
                || $data['owner_id'] === $request->user()->id,
                422,
                'Penanggung jawab opportunity harus user Sales yang aktif.'
            );
        } else {
            $data['owner_id'] = $request->user()->id;
        }
        $data['participants'] = $participantIds
            ->reject(fn ($id) => $id === (int) $data['owner_id'])
            ->values()
            ->all();

        $normalizedItems = $items->map(function (array $item) {
            $product = ! empty($item['product_id']) ? Product::find($item['product_id']) : null;
            $quantity = (int) ($item['quantity'] ?? 0);
            $targetPrice = (float) ($item['target_price'] ?? 0);

            return [
                'product_id' => $product?->id,
                'product_name' => ($item['product_name'] ?? null) ?: $product?->name,
                'photo_path' => $item['photo']->store('opportunity-products', 'public'),
                'quantity' => $quantity,
                'quantity_unit' => $item['quantity_unit'] ?? $product?->unit ?? 'pcs',
                'target_price' => $item['target_price'] ?? null,
                'unit_price' => 0,
                'subtotal' => 0,
                'target_subtotal' => $quantity * $targetPrice,
            ];
        });
        $firstItem = $normalizedItems->first();
        $data['product_id'] = $firstItem['product_id'];
        $data['product_name'] = $firstItem['product_name'];
        $data['estimated_quantity'] = $firstItem['quantity'];
        $data['quantity_unit'] = $firstItem['quantity_unit'];
        $data['target_price'] = $firstItem['target_price'];
        $data['offered_price'] = null;
        $data['estimated_value'] = $normalizedItems->sum('target_subtotal');
        $normalizedItems = $normalizedItems->map(function (array $item) {
            unset($item['target_subtotal']);
            return $item;
        });
        $stage = PipelineStage::where('pipeline_id', $data['pipeline_id'])->orderBy('position')->firstOrFail();
        $data['pipeline_stage_id'] = $stage->id;
        $data['probability'] = $stage->probability;
        $opportunity = DB::transaction(function () use ($data, $normalizedItems) {
            $opportunity = Opportunity::create($data);
            $opportunity->items()->createMany($normalizedItems->all());
            return $opportunity;
        });
        foreach ($opportunity->participants ?? [] as $participantId) {
            $notifier->send(
                $participantId,
                'opportunity_invitation',
                'Anda dilibatkan dalam opportunity',
                $request->user()->name.' melibatkan Anda pada "'.$opportunity->title.'".',
                route('opportunities.show', $opportunity),
                ['opportunity_id' => $opportunity->id]
            );
        }
        return redirect()->route('opportunities.show', $opportunity)->with('success', 'Opportunity berhasil dibuat.');
    }

    public function show(Opportunity $opportunity)
    {
        $this->authorizeOpportunity($opportunity);
        $opportunity->load(['customer', 'pipeline.stages.rules', 'stage', 'owner', 'items.product', 'activities.user', 'activities.attachments', 'tasks.assignees', 'stageHistories.fromStage', 'stageHistories.toStage', 'stageHistories.changedBy']);
        $quotationStage = $opportunity->pipeline->stages->firstWhere('slug', 'quotation');
        $canSetQuotation = $quotationStage
            && $opportunity->pipeline_stage_id === $quotationStage->id
            && ! in_array($opportunity->status, ['won', 'lost'], true);
        $productHistory = AuditLog::query()
            ->with('user')
            ->where('auditable_type', OpportunityItem::class)
            ->whereIn('auditable_id', $opportunity->items->pluck('id'))
            ->latest()
            ->get()
            ->values();
        $quotationHistory = $productHistory->filter(fn (AuditLog $log) => $log->action === 'updated' && data_get($log->old_values, 'unit_price') !== data_get($log->new_values, 'unit_price'))->values();

        return view('opportunities.show', compact('opportunity', 'canSetQuotation', 'quotationHistory', 'productHistory'));
    }

    public function updateQuotation(Request $request, Opportunity $opportunity)
    {
        $this->authorizeOpportunity($opportunity);
        $opportunity->load(['pipeline.stages', 'stage', 'items']);
        $quotationStage = $opportunity->pipeline->stages->firstWhere('slug', 'quotation');
        abort_unless(
            $quotationStage
            && $opportunity->pipeline_stage_id === $quotationStage->id
            && ! in_array($opportunity->status, ['won', 'lost'], true),
            422,
            'Harga penawaran hanya dapat diubah pada tahap Quotation.'
        );

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $opportunity) {
            foreach ($data['items'] as $input) {
                $item = $opportunity->items->firstWhere('id', (int) $input['id']);
                abort_unless($item, 422, 'Produk quotation tidak valid.');
                $price = (float) $input['unit_price'];
                $item->update([
                    'unit_price' => $price,
                    'subtotal' => (float) $item->quantity * $price,
                ]);
            }

            $opportunity->update([
                'offered_price' => $opportunity->items()->orderBy('id')->value('unit_price'),
            ]);
        });

        return back()->with('success', 'Harga penawaran tersimpan. Riwayat harga tetap dapat dilihat.');
    }

    public function updateGeneralInfo(Request $request, Opportunity $opportunity)
    {
        $this->authorizeOpportunity($opportunity);

        $data = $request->validate([
            'current_supplier' => ['nullable', 'string', 'max:255'],
            'competitor' => ['nullable', 'string', 'max:255'],
            'lead_source' => ['nullable', Rule::in(['website', 'whatsapp', 'referral', 'sales_visit', 'event', 'ads', 'social_media', 'marketplace', 'database', 'telemarketing', 'walk_in', 'other'])],
        ]);

        $opportunity->update($data);

        return back()->with('success', 'Informasi umum opportunity berhasil diperbarui.');
    }

    public function updateItemStatus(Request $request, Opportunity $opportunity, OpportunityItem $item)
    {
        $this->authorizeOpportunity($opportunity);
        abort_unless((int) $item->opportunity_id === (int) $opportunity->id, 404);

        $data = $request->validate([
            'deal_status' => ['required', Rule::in(['on_process', 'deal', 'rejected'])],
        ]);

        $item->update([
            'deal_status' => $data['deal_status'],
            'deal_status_updated_by' => $request->user()->id,
            'deal_status_updated_at' => now(),
        ]);

        $label = match ($data['deal_status']) {
            'deal' => 'Deal',
            'rejected' => 'Ditolak',
            default => 'Diproses',
        };

        return back()->with('success', 'Status '.$item->product_name.' diubah menjadi '.$label.'.');
    }

    public function storeItem(Request $request, Opportunity $opportunity)
    {
        $this->authorizeOpportunity($opportunity);
        $data = $request->validate([
            'product_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'quantity_unit' => ['required', Rule::in(array_keys(Opportunity::QUANTITY_UNITS))],
            'target_price' => ['nullable', 'numeric', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $quantity = (int) $data['quantity'];
        $unitPrice = (float) ($data['unit_price'] ?? 0);
        $data['photo_path'] = $data['photo']->store('opportunity-products', 'public');
        unset($data['photo']);
        $item = $opportunity->items()->create([
            ...$data,
            'unit_price' => $unitPrice,
            'subtotal' => $quantity * $unitPrice,
            'deal_status' => 'on_process',
        ]);

        $targetTotal = (float) $opportunity->items()
            ->selectRaw('COALESCE(SUM(quantity * COALESCE(target_price, 0)), 0) as target_total')
            ->value('target_total');
        $opportunity->update(['estimated_value' => $targetTotal]);

        return back()->with('success', $item->product_name.' ditambahkan dan berstatus Diproses.');
    }

    public function updateItemPrice(Request $request, Opportunity $opportunity, OpportunityItem $item)
    {
        $this->authorizeOpportunity($opportunity);
        abort_unless((int) $item->opportunity_id === (int) $opportunity->id, 404);
        $data = $request->validate(['unit_price' => ['required', 'numeric', 'min:0']]);
        $price = (float) $data['unit_price'];

        $item->update([
            'unit_price' => $price,
            'subtotal' => (float) $item->quantity * $price,
        ]);
        $opportunity->update([
            'offered_price' => $opportunity->items()->orderBy('id')->value('unit_price'),
        ]);

        return back()->with('success', 'Harga penawaran '.$item->product_name.' diperbarui.');
    }

    public function updateItem(Request $request, Opportunity $opportunity, OpportunityItem $item)
    {
        $this->authorizeOpportunity($opportunity);
        abort_unless((int) $item->opportunity_id === (int) $opportunity->id, 404);
        $data = $request->validate([
            'product_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'quantity_unit' => ['required', Rule::in(array_keys(Opportunity::QUANTITY_UNITS))],
            'target_price' => ['nullable', 'numeric', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'photo' => [$item->photo_path ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $oldPhotoPath = $item->photo_path;
        if (isset($data['photo'])) {
            $data['photo_path'] = $data['photo']->store('opportunity-products', 'public');
        }
        unset($data['photo']);
        $data['unit_price'] = (float) ($data['unit_price'] ?? 0);
        $data['subtotal'] = (int) $data['quantity'] * $data['unit_price'];
        $item->update($data);
        if (isset($data['photo_path']) && $oldPhotoPath) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        $opportunity->update([
            'estimated_value' => (float) $opportunity->items()
                ->selectRaw('COALESCE(SUM(quantity * COALESCE(target_price, 0)), 0) as target_total')
                ->value('target_total'),
            'offered_price' => $opportunity->items()->orderBy('id')->value('unit_price'),
        ]);
        return back()->with('success', 'Data produk '.$item->product_name.' diperbarui.');
    }

    public function moveStage(Request $request, Opportunity $opportunity, StageTransitionService $service)
    {
        $this->authorizeOpportunity($opportunity);
        $stageData = $request->validate(['stage_id' => ['required', 'exists:pipeline_stages,id']]);
        $target = PipelineStage::findOrFail($stageData['stage_id']);
        $data = $request->validate([
            'reason' => [$target->is_lost ? 'required' : 'nullable', 'string', 'max:1000'],
            'lost_reason' => [$target->is_lost ? 'required' : 'nullable', Rule::in(['price', 'competitor', 'budget', 'cancelled', 'no_response', 'other'])],
        ], [
            'reason.required' => 'Detail alasan Lost wajib diisi.',
            'lost_reason.required' => 'Kategori alasan Lost wajib dipilih.',
        ]);
        $service->move($opportunity, $target, $data['reason'] ?? null, $request->user()->id, $data['lost_reason'] ?? null);
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Stage opportunity berhasil dipindahkan.',
                'stage_id' => (int) $target->id,
            ]);
        }
        return back()->with('success', 'Stage opportunity berhasil dipindahkan.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'exists:customers,id'], 'pipeline_id' => ['required', 'exists:pipelines,id'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'title' => ['required'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.product_name' => ['nullable', 'string', 'max:255', 'required_without:items.*.product_id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.quantity_unit' => ['required', Rule::in(array_keys(Opportunity::QUANTITY_UNITS))],
            'items.*.target_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'current_supplier' => ['nullable'], 'competitor' => ['nullable'], 'expected_close_date' => ['nullable', 'date'],
            'next_action' => ['nullable'], 'next_follow_up_at' => ['nullable', 'date'],
            'lead_source' => ['nullable', Rule::in(['website', 'whatsapp', 'referral', 'sales_visit', 'event', 'ads', 'social_media', 'marketplace', 'database', 'telemarketing', 'walk_in', 'other'])],
            'priority' => ['required', 'in:low,medium,high'],
        ]);
    }

    private function authorizeOpportunity(Opportunity $opportunity): void { abort_unless(Opportunity::visibleTo(auth()->user())->whereKey($opportunity->id)->exists(), 403); }
    private function formData(Opportunity $opportunity): array
    {
        $user = auth()->user();
        $canAssignOwner = $this->canAssignOwner($user);

        return [
            'opportunity' => $opportunity,
            'customers' => Customer::visibleTo($user)->orderBy('company_name')->get(),
            'pipelines' => Pipeline::where('is_active', true)->with('stages')->get(),
            'users' => $canAssignOwner
                ? User::where('is_active', true)->whereHas('roles', fn ($query) => $query->where('slug', 'sales'))->orderBy('name')->get()
                : collect([$user]),
            'canAssignOwner' => $canAssignOwner,
            'collaborationUsers' => User::where('is_active', true)
                ->whereKeyNot($user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id', 'user_type', 'authority_level']),
        ];
    }

    private function canAssignOwner(User $user): bool
    {
        return $user->isMasterAdmin()
            || in_array($user->authority_level, ['manager', 'supervisor'], true)
            || $user->roles()->where('slug', 'csa')->exists();
    }
}
