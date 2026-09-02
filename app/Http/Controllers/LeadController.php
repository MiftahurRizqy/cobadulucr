<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Attachment;
use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Models\SystemSetting;
use App\Support\CustomerDuplicateDetector;
use App\Support\BusinessUnitResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $leads = Lead::query()->visibleTo($request->user())->with('owner')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('brand_name', 'like', "%$s%")->orWhere('company_name', 'like', "%$s%")->orWhere('contact_name', 'like', "%$s%")->orWhere('lead_id', 'like', "%$s%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(15)->withQueryString();
        return view('leads.index', compact('leads'));
    }

    public function create() { return view('leads.form', $this->formData(new Lead)); }

    public function store(Request $request, CustomerDuplicateDetector $duplicateDetector, BusinessUnitResolver $businessUnits)
    {
        $request->validate(['business_type_custom' => ['nullable', 'string', 'max:255']]);
        if ($request->user()->isSales()) {
            $request->merge(['owner_id' => $request->user()->id]);
        }
        $data = $this->validated($request);
        $businessUnit = $this->resolveBusinessUnit($request, $data, $businessUnits);
        $data['business_type'] = $businessUnit?->name;
        $data['business_unit_id'] = $businessUnit?->id;
        $data = $this->normalizeProductInterests($data);
        $this->guardDuplicates($request, $duplicateDetector, $data);
        $data['whatsapp'] = $data['phone'];
        $data['created_by'] = $request->user()->id;
        $lead = Lead::create($data);
        $this->syncCollaborators($lead, $request);
        return redirect()->route('customers.index', ['view' => 'prospects'])->with('success', 'Lead berhasil dibuat.');
    }

    public function edit(Lead $lead)
    {
        abort_unless(Lead::visibleTo(auth()->user())->whereKey($lead->id)->exists(), 403);
        $lead->load('collaborators');
        return view('leads.form', $this->formData($lead));
    }

    public function update(Request $request, Lead $lead, CustomerDuplicateDetector $duplicateDetector, BusinessUnitResolver $businessUnits)
    {
        $request->validate(['business_type_custom' => ['nullable', 'string', 'max:255']]);
        abort_unless(Lead::visibleTo($request->user())->whereKey($lead->id)->exists(), 403);
        if ($request->user()->isSales()) {
            $request->merge(['owner_id' => $lead->owner_id]);
        }
        $data = $this->validated($request);
        $businessUnit = $this->resolveBusinessUnit($request, $data, $businessUnits);
        $data['business_type'] = $businessUnit?->name;
        $data['business_unit_id'] = $businessUnit?->id;
        if (! $request->has('product_interests') && ! $request->has('product_interest')) {
            $data['product_interests'] = $lead->interestItems();
        }
        $data = $this->normalizeProductInterests($data);
        $this->guardDuplicates($request, $duplicateDetector, $data, $lead);
        $data['whatsapp'] = $data['phone'];
        $lead->update($data);
        $this->syncCollaborators($lead, $request);
        return redirect()->route('customers.index', ['view' => 'prospects'])->with('success', 'Lead diperbarui.');
    }

    public function convert(Request $request, Lead $lead)
    {
        abort_unless(Lead::visibleTo($request->user())->whereKey($lead->id)->exists(), 403);
        abort_if($lead->status === 'converted', 422, 'Lead ini sudah menjadi customer.');

        $data = $request->validate([
            'legal_name' => [SystemSetting::bool('customer_legal_name_required', true, $request->user()) ? 'required' : 'nullable', 'string', 'max:255'],
            'npwp' => [SystemSetting::bool('customer_npwp_required', true, $request->user()) ? 'required' : 'nullable', 'string', 'max:50'],
            'supporting_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'legal_name.required' => 'Nama legal wajib diisi sebelum lead dijadikan customer.',
            'npwp.required' => 'NPWP wajib diisi sebelum lead dijadikan customer.',
            'supporting_document.mimes' => 'Dokumen pendukung harus berupa PDF, JPG, PNG, atau WebP.',
            'supporting_document.max' => 'Ukuran dokumen pendukung maksimal 10 MB.',
        ]);

        $customer = DB::transaction(function () use ($lead, $request) {
            $customer = Customer::create([
                'converted_from_lead_id' => $lead->id,
                'company_name' => $lead->company_name ?: $lead->brand_name,
                'brand_name' => $lead->brand_name,
                'legal_name' => (string) $request->string('legal_name')->trim(),
                'npwp' => (string) $request->string('npwp')->trim(),
                'phone' => $lead->phone,
                'email' => $lead->email,
                'city' => $lead->city,
                'address' => $lead->address,
                'area_id' => $lead->area_id,
                'business_unit_id' => $lead->business_unit_id,
                'business_type' => $lead->business_type,
                'became_customer_at' => now(),
                'product_interest' => $lead->product_interest,
                'product_interests' => $lead->interestItems(),
                'estimated_need' => $lead->estimated_need,
                'estimated_need_unit' => $lead->estimated_need_unit,
                'sales_owner_id' => $lead->owner_id,
                'status' => 'active',
                'next_follow_up_at' => $lead->next_follow_up_at,
                'created_by' => $request->user()->id,
            ]);
            $assignments = $lead->collaborators()->pluck('users.id')
                ->reject(fn ($id) => (int) $id === (int) $lead->owner_id)
                ->mapWithKeys(fn ($id) => [$id => ['responsibility' => 'collaborator']])
                ->put($lead->owner_id, ['responsibility' => 'owner'])
                ->all();
            $customer->assignedUsers()->sync($assignments);
            AuditLog::recordRelation($customer, 'assigned_users', [], array_keys($assignments));

            if ($file = $request->file('supporting_document')) {
                $path = $file->store('customer-documents/'.now()->format('Y/m'), 'public');
                Attachment::create([
                    'attachable_type' => Customer::class,
                    'attachable_id' => $customer->id,
                    'uploaded_by' => $request->user()->id,
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                ]);
            }

            $lead->update(['status_before_conversion' => $lead->status, 'status' => 'converted']);
            return $customer;
        });
        return redirect()->route('customers.show', $customer)->with('success', 'Lead berhasil dikonversi menjadi customer.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'company_name' => ['nullable', 'max:255'], 'brand_name' => ['required', 'max:255'],
            'contact_name' => ['required', 'max:255'], 'phone' => ['required', 'max:30'],
            'whatsapp' => ['nullable', 'max:30'], 'email' => ['nullable', 'email'], 'city' => ['nullable'],
            'address' => ['nullable', 'string', 'max:2000'],
            'province' => ['nullable'], 'area_id' => ['nullable', 'exists:areas,id'],
            'business_unit_id' => ['nullable', 'exists:business_units,id'], 'owner_id' => ['required', 'exists:users,id'],
            'source' => ['required'], 'business_type' => ['nullable'],
            'product_interests' => ['nullable', 'array'],
            'product_interests.*.product_name' => ['nullable', 'string', 'max:255'],
            'product_interests.*.estimated_need' => ['nullable', 'numeric', 'min:0'],
            'product_interests.*.estimated_need_unit' => ['nullable', 'in:pcs,pack,roll,ctn,set,kg,bal'],
            'product_interest' => ['nullable', 'string', 'max:255'],
            'estimated_need' => ['nullable', 'numeric', 'min:0'],
            'estimated_need_unit' => ['nullable', 'in:pcs,pack,roll,ctn,set,kg,bal'],
            'notes' => ['nullable'], 'status' => ['required', 'in:'.implode(',', array_keys(Lead::EDITABLE_STATUSES))],
            'next_follow_up_at' => ['nullable', 'date'],
            'collaborator_ids' => ['nullable', 'array'],
            'collaborator_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);
    }

    private function normalizeProductInterests(array $data): array
    {
        $submittedItems = $data['product_interests'] ?? [];
        if (empty($submittedItems) && filled($data['product_interest'] ?? null)) {
            $submittedItems = [[
                'product_name' => $data['product_interest'],
                'estimated_need' => $data['estimated_need'] ?? null,
                'estimated_need_unit' => $data['estimated_need_unit'] ?? 'pcs',
            ]];
        }

        $items = collect($submittedItems)
            ->map(fn ($item) => [
                'product_name' => trim((string) ($item['product_name'] ?? '')),
                'estimated_need' => filled($item['estimated_need'] ?? null) ? (int) $item['estimated_need'] : null,
                'estimated_need_unit' => $item['estimated_need_unit'] ?? 'pcs',
            ])
            ->filter(fn ($item) => $item['product_name'] !== '')
            ->values();

        $first = $items->first();
        $data['product_interests'] = $items->all() ?: null;
        $data['product_interest'] = $first['product_name'] ?? null;
        $data['estimated_need'] = $first['estimated_need'] ?? null;
        $data['estimated_need_unit'] = $first['estimated_need_unit'] ?? 'pcs';

        return $data;
    }

    private function resolveBusinessUnit(Request $request, array $data, BusinessUnitResolver $resolver): ?BusinessUnit
    {
        $selectedName = $data['business_type'] ?? null;

        if (! $selectedName && ! empty($data['business_unit_id'])) {
            $selectedName = BusinessUnit::whereKey($data['business_unit_id'])->value('name');
        }

        if (! $selectedName && $request->user()->isSales()) {
            $selectedName = $request->user()->businessUnits()->value('name');
        }

        return $resolver->resolve($selectedName, $request->input('business_type_custom'), $request->user()->isMasterAdmin());
    }

    private function formData(Lead $lead): array
    {
        return [
            'lead' => $lead,
            'isSales' => auth()->user()->isSales(),
            'canInvite' => auth()->user()->canAccess('leads.invite'),
            'users' => User::with('roles')->where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['sales', 'telesales']))
                ->orderBy('name')->get(),
            'areas' => Area::orderBy('name')->get(),
            'businessUnits' => app(BusinessUnitResolver::class)->options(),
        ];
    }

    private function syncCollaborators(Lead $lead, Request $request): void
    {
        if (! $request->user()->canAccess('leads.invite')) return;

        $ids = User::query()
            ->where('is_active', true)
            ->whereIn('id', collect($request->input('collaborator_ids', []))->map(fn ($id) => (int) $id))
            ->whereKeyNot($lead->owner_id)
            ->whereHas('roles', fn ($roles) => $roles->whereIn('slug', ['sales', 'telesales']))
            ->pluck('id');

        $oldIds = $lead->collaborators()->pluck('users.id');
        $lead->collaborators()->sync($ids);
        AuditLog::recordRelation($lead, 'collaborators', $oldIds, $ids);
    }

    private function guardDuplicates(Request $request, CustomerDuplicateDetector $detector, array $data, ?Lead $lead = null): void
    {
        if ($request->boolean('duplicate_confirmed')) return;
        $matches = $detector->detect($request->user(), $data, null, $lead);
        if ($matches->isNotEmpty()) {
            $request->merge(['duplicate_confirmed' => 1]);
            throw \Illuminate\Validation\ValidationException::withMessages([
                'duplicate' => 'Data serupa ditemukan: '.$matches->first()['name'].'. Periksa peringatan, lalu klik Simpan kembali jika memang berbeda.',
            ]);
        }
    }

}
