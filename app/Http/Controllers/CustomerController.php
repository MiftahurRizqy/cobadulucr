<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Support\CustomerDuplicateDetector;
use App\Support\BusinessUnitResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->string('view')->value() === 'prospects' ? 'prospects' : 'customers';
        $search = trim((string) $request->search);

        $prospectCount = $request->user()->canAccess('leads.view')
            ? Lead::query()->visibleTo($request->user())
                ->whereNotIn('status', ['converted', 'leads_hold'])
                ->whereDoesntHave('convertedCustomer')
                ->count()
            : 0;
        $customerCount = Customer::query()->visibleTo($request->user())->count();
        $areas = Area::query()->orderBy('name')->get(['id', 'name']);
        $customerTypes = app(BusinessUnitResolver::class)->options();

        if ($view === 'prospects' && $request->user()->canAccess('leads.view')) {
            $filterOwners = User::query()
                ->whereIn('id', Lead::query()->visibleTo($request->user())->select('owner_id'))
                ->orderBy('name')
                ->get(['id', 'name']);
            $records = Lead::query()->visibleTo($request->user())
                ->whereNotIn('status', ['converted', 'leads_hold'])
                ->whereDoesntHave('convertedCustomer')
                ->with(['owner', 'area'])
                ->when($search, fn ($q, $s) => $q->where(fn ($q) => $q
                    ->where('company_name', 'like', "%$s%")
                    ->orWhere('contact_name', 'like', "%$s%")
                    ->orWhere('phone', 'like', "%$s%")
                    ->orWhere('lead_id', 'like', "%$s%")))
                ->when($request->status, fn ($q, $s) => $q->where('status', $s))
                ->when($request->area_id, fn ($q, $id) => $q->where('area_id', $id))
                ->when($request->business_type, fn ($q, $type) => $q->where('business_type', $type))
                ->when($request->owner_id, fn ($q, $id) => $q->where('owner_id', $id))
                ->when($request->follow_up === 'overdue', fn ($q) => $q->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<', now()))
                ->when($request->follow_up === 'scheduled', fn ($q) => $q->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '>=', now()))
                ->when($request->follow_up === 'none', fn ($q) => $q->whereNull('next_follow_up_at'))
                ->latest()->paginate(15)->withQueryString();

            return view('customers.index', compact('records', 'view', 'prospectCount', 'customerCount', 'areas', 'filterOwners', 'customerTypes'));
        }

        $view = 'customers';
        $filterOwners = User::query()
            ->whereIn('id', Customer::query()->visibleTo($request->user())->select('sales_owner_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
        $customers = Customer::query()->visibleTo($request->user())->with(['salesOwner', 'area'])
            ->when($search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('company_name', 'like', "%$s%")
                ->orWhere('brand_name', 'like', "%$s%")
                ->orWhere('phone', 'like', "%$s%")
                ->orWhere('customer_id', 'like', "%$s%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->area_id, fn ($q, $id) => $q->where('area_id', $id))
            ->when($request->business_type, fn ($q, $type) => $q->where('business_type', $type))
            ->when($request->owner_id, fn ($q, $id) => $q->where('sales_owner_id', $id))
            ->when($request->follow_up === 'overdue', fn ($q) => $q->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<', now()))
            ->when($request->follow_up === 'scheduled', fn ($q) => $q->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '>=', now()))
            ->when($request->follow_up === 'none', fn ($q) => $q->whereNull('next_follow_up_at'))
            ->latest()->paginate(15)->withQueryString();
        $records = $customers;

        return view('customers.index', compact('records', 'view', 'prospectCount', 'customerCount', 'areas', 'filterOwners', 'customerTypes'));
    }

    public function create() { return view('customers.form', $this->formData(new Customer)); }

    public function store(Request $request, CustomerDuplicateDetector $duplicateDetector, BusinessUnitResolver $businessUnits)
    {
        $request->validate(['business_type_custom' => ['nullable', 'string', 'max:255']]);
        if ($request->user()->isSales()) {
            $request->merge([
                'sales_owner_id' => $request->user()->id,
                'assigned_user_ids' => [$request->user()->id],
            ]);
        }
        $data = $this->validated($request);
        $businessUnit = $this->resolveBusinessUnit($request, $data, $businessUnits);
        $data['business_type'] = $businessUnit?->name;
        $data['business_unit_id'] = $businessUnit?->id;
        $this->guardDuplicates($request, $duplicateDetector, $data);
        $data['sales_owner_id'] ??= $request->user()->id;
        $owner = User::with('manager.manager')->find($data['sales_owner_id']);
        $data['supervisor_id'] ??= $owner?->manager_id;
        $data['manager_id'] ??= $owner?->manager?->manager_id;
        $data['created_by'] = $request->user()->id;
        $customer = DB::transaction(function () use ($data, $request) {
            $customer = Customer::create($data);
            $customer->assignedUsers()->sync(collect($request->input('assigned_user_ids', []))->push($data['sales_owner_id'])->filter()->unique());
            return $customer;
        });
        return redirect()->route('customers.show', $customer)->with('success', 'Customer berhasil dibuat.');
    }

    public function show(Request $request, Customer $customer)
    {
        $this->authorizeCustomer($customer);
        $customer->load(['salesOwner', 'area', 'contacts', 'tasks.assignees', 'assignedUsers', 'attachments.uploader']);
        $opportunityOptions = $customer->opportunities()
            ->with('stage')
            ->latest('updated_at')
            ->get();
        $selectedOpportunityId = $request->integer('opportunity');
        $selectedOpportunity = $customer->opportunities()
            ->with(['stage', 'items', 'pipeline.stages'])
            ->when($selectedOpportunityId, fn ($query) => $query->whereKey($selectedOpportunityId))
            ->latest('updated_at')
            ->first();
        if (! $selectedOpportunity && $selectedOpportunityId) {
            $selectedOpportunity = $customer->opportunities()
                ->with(['stage', 'items', 'pipeline.stages'])
                ->latest('updated_at')
                ->first();
        }
        $opportunityStats = [
            'total' => $customer->opportunities()->count(),
            'processing' => \App\Models\OpportunityItem::query()
                ->whereHas('opportunity', fn ($query) => $query->where('customer_id', $customer->id))
                ->where('deal_status', 'on_process')->count(),
            'deal' => \App\Models\OpportunityItem::query()
                ->whereHas('opportunity', fn ($query) => $query->where('customer_id', $customer->id))
                ->where('deal_status', 'deal')->count(),
        ];
        $activities = Activity::query()->visibleTo(request()->user())
            ->where('customer_id', $customer->id)
            ->with(['user', 'opportunity', 'approvalDetail', 'attachments', 'comments.user'])
            ->latest('occurred_at')
            ->paginate(8, ['*'], 'activity_page');
        $participantUsers = User::query()
            ->whereIn('id', $activities->getCollection()->flatMap(fn (Activity $activity) => $activity->participants ?? [])->unique())
            ->get(['id', 'name', 'employee_id'])
            ->keyBy('id');
        $activityDiscussionUsers = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id']);
        $canReviewEvidenceIntegrity = ! request()->user()->isSales();
        return view('customers.show', compact('customer', 'activities', 'opportunityOptions', 'selectedOpportunity', 'opportunityStats', 'participantUsers', 'activityDiscussionUsers', 'canReviewEvidenceIntegrity'));
    }

    public function storeDocument(Request $request, Customer $customer)
    {
        $this->authorizeCustomer($customer);
        $data = $request->validate([
            'supporting_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'supporting_document.required' => 'Pilih dokumen yang akan ditambahkan.',
            'supporting_document.mimes' => 'Dokumen harus berupa PDF, JPG, PNG, atau WebP.',
            'supporting_document.max' => 'Ukuran dokumen maksimal 10 MB.',
        ]);

        $file = $data['supporting_document'];
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

        return redirect()->route('customers.show', $customer)->with('success', 'Dokumen customer berhasil ditambahkan.');
    }

    public function edit(Customer $customer)
    {
        $this->authorizeCustomer($customer);
        $customer->load('assignedUsers');
        return view('customers.form', $this->formData($customer));
    }

    public function update(Request $request, Customer $customer, CustomerDuplicateDetector $duplicateDetector, BusinessUnitResolver $businessUnits)
    {
        $request->validate(['business_type_custom' => ['nullable', 'string', 'max:255']]);
        $this->authorizeCustomer($customer);
        if ($request->user()->isSales()) {
            $request->merge(['sales_owner_id' => $customer->sales_owner_id]);
        }
        $data = $this->validated($request);
        $businessUnit = $this->resolveBusinessUnit($request, $data, $businessUnits);
        $data['business_type'] = $businessUnit?->name;
        $data['business_unit_id'] = $businessUnit?->id;
        $this->guardDuplicates($request, $duplicateDetector, $data, $customer);
        $customer->update($data);
        if ($request->has('assigned_user_ids')) {
            $customer->assignedUsers()->sync(collect($request->input('assigned_user_ids', []))->push($data['sales_owner_id'] ?? null)->filter()->unique());
        }
        return redirect()->route('customers.show', $customer)->with('success', 'Customer diperbarui.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'company_name' => ['required', 'max:255'], 'brand_name' => ['nullable'], 'legal_name' => ['nullable'],
            'npwp' => ['nullable'], 'phone' => ['required', 'max:30'], 'email' => ['nullable', 'email'],
            'address' => ['nullable'], 'shipping_address' => ['nullable'], 'billing_address' => ['nullable'],
            'city' => ['nullable'], 'area_id' => ['nullable', 'exists:areas,id'], 'business_unit_id' => ['nullable', 'exists:business_units,id'],
            'business_type' => ['nullable', 'string', 'max:255'],
            'sales_owner_id' => ['nullable', 'exists:users,id'], 'supervisor_id' => ['nullable', 'exists:users,id'], 'manager_id' => ['nullable', 'exists:users,id'],
            'status' => ['required', 'in:pareto,active,inactive,risky'], 'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'payment_term_days' => ['nullable', 'integer', 'min:0'],
            'estimated_monthly_purchase' => ['nullable', 'numeric', 'min:0'],
            'next_follow_up_at' => ['nullable', 'date'],
            'assigned_user_ids' => ['nullable', 'array'], 'assigned_user_ids.*' => ['exists:users,id'],
        ]);
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

    private function authorizeCustomer(Customer $customer): void
    {
        abort_unless(Customer::visibleTo(auth()->user())->whereKey($customer->id)->exists(), 403);
    }

    private function guardDuplicates(Request $request, CustomerDuplicateDetector $detector, array $data, ?Customer $customer = null): void
    {
        if ($request->boolean('duplicate_confirmed')) return;
        $matches = $detector->detect($request->user(), $data, $customer);
        if ($matches->isNotEmpty()) {
            $request->merge(['duplicate_confirmed' => 1]);
            throw \Illuminate\Validation\ValidationException::withMessages([
                'duplicate' => 'Data serupa ditemukan: '.$matches->first()['name'].'. Periksa peringatan, lalu klik Simpan kembali jika memang berbeda.',
            ]);
        }
    }

    private function formData(Customer $customer): array
    {
        return [
            'customer' => $customer,
            'isSales' => auth()->user()->isSales(),
            'users' => User::with('roles')->where('is_active', true)->orderBy('name')->get(),
            'areas' => Area::orderBy('name')->get(),
            'businessUnits' => app(BusinessUnitResolver::class)->options(),
        ];
    }
}
