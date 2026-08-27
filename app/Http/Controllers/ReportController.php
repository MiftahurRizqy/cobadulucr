<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Task;
use App\Models\User;
use App\Support\BusinessUnitResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        [$dateFrom, $dateTo] = $this->periodRange($request);

        $leadQuery = Lead::query()
            ->visibleTo($user)
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function (Builder $query) use ($search) {
                    $query->where('leads.company_name', 'like', "%{$search}%")
                        ->orWhere('leads.lead_id', 'like', "%{$search}%")
                        ->orWhere('leads.brand_name', 'like', "%{$search}%")
                        ->orWhere('leads.pic_name', 'like', "%{$search}%")
                        ->orWhereHas('convertedCustomer', fn (Builder $customer) => $customer
                            ->where('customer_id', 'like', "%{$search}%"));
                });
            })
            ->when($dateFrom, fn (Builder $query) => $query->whereDate('leads.created_at', '>=', $dateFrom))
            ->when($dateTo, fn (Builder $query) => $query->whereDate('leads.created_at', '<=', $dateTo))
            ->when($request->owner_id, fn (Builder $query, $ownerId) => $query->where('leads.owner_id', $ownerId))
            ->when($request->area_id, fn (Builder $query, $areaId) => $query->where('leads.area_id', $areaId))
            ->when($request->business_type, fn (Builder $query, $businessType) => $query->where('leads.business_type', $businessType))
            ->when($request->source, fn (Builder $query, $source) => $query->where('leads.source', $source));

        // Status/kartu hanya menyaring tabel detail. Ringkasan KPI tetap dihitung dari
        // kumpulan lead yang sama berdasarkan periode, sales, area, jenis, dan sumber.
        $detailLeadQuery = (clone $leadQuery)
            ->when($request->lead_status, fn (Builder $query, $status) => $query->where('leads.status', $status))
            ->when($request->conversion_scope === 'incoming', fn (Builder $query) => $query->whereDoesntHave('convertedCustomer'))
            ->when($request->conversion_scope === 'leads_adds', fn (Builder $query) => $query->where('leads.status', 'leads_adds'))
            ->when($request->conversion_scope === 'converted', fn (Builder $query) => $query->whereHas('convertedCustomer'))
            ->when($request->conversion_scope === 'deal', fn (Builder $query) => $query->whereHas(
                'convertedCustomer.opportunityItems',
                fn (Builder $items) => $items->where('deal_status', 'deal')
            ));

        $sourceStats = (clone $leadQuery)
            ->leftJoin('customers', 'customers.converted_from_lead_id', '=', 'leads.id')
            ->leftJoin('opportunities', 'opportunities.customer_id', '=', 'customers.id')
            ->leftJoin('opportunity_items', 'opportunity_items.opportunity_id', '=', 'opportunities.id')
            ->selectRaw("COALESCE(NULLIF(leads.source, ''), 'other') as source_key")
            ->selectRaw('COUNT(DISTINCT leads.id) as total_leads')
            ->selectRaw('COUNT(DISTINCT customers.id) as converted_customers')
            ->selectRaw("COUNT(DISTINCT CASE WHEN opportunity_items.deal_status = 'deal' THEN customers.id END) as customers_with_deal")
            ->selectRaw("COUNT(DISTINCT CASE WHEN opportunity_items.deal_status = 'deal' THEN opportunity_items.id END) as deal_items")
            ->groupByRaw("COALESCE(NULLIF(leads.source, ''), 'other')")
            ->orderByDesc('total_leads')
            ->get()
            ->map(function ($row) {
                $row->label = $this->sourceLabel($row->source_key);
                $row->conversion_rate = $row->total_leads > 0
                    ? round(($row->converted_customers / $row->total_leads) * 100, 1)
                    : 0;
                $row->deal_rate = $row->converted_customers > 0
                    ? round(($row->customers_with_deal / $row->converted_customers) * 100, 1)
                    : 0;

                return $row;
            });

        $leadIds = (clone $leadQuery)->select('id');
        $customerQuery = Customer::query()
            ->visibleTo($user)
            ->whereIn('converted_from_lead_id', $leadIds);

        $totalLeads = (clone $leadQuery)->count();
        $leadsAdds = (clone $leadQuery)->where('status', 'leads_adds')->count();
        $activeLeads = (clone $leadQuery)->where('status', '!=', 'converted')->count();
        $convertedCustomers = (clone $customerQuery)->count();
        $customersWithDeal = (clone $customerQuery)
            ->whereHas('opportunityItems', fn (Builder $query) => $query->where('deal_status', 'deal'))
            ->count();
        $dealItems = OpportunityItem::query()
            ->where('deal_status', 'deal')
            ->whereHas('opportunity.customer', fn (Builder $query) => $query->whereIn('converted_from_lead_id', (clone $leadIds)))
            ->count();

        $conversionRate = $totalLeads > 0 ? round(($convertedCustomers / $totalLeads) * 100, 1) : 0;
        $dealRate = $convertedCustomers > 0 ? round(($customersWithDeal / $convertedCustomers) * 100, 1) : 0;
        [$previousFrom, $previousTo] = $this->previousPeriodRange($dateFrom, $dateTo);

        $conversionRows = (clone $detailLeadQuery)
            ->with([
                'owner:id,name',
                'area:id,name',
                'businessUnit:id,name',
                'convertedCustomer' => fn ($query) => $query
                    ->withCount(['opportunityItems as deal_items_count' => fn ($items) => $items->where('deal_status', 'deal')])
                    ->withSum(['opportunityItems as deal_value' => fn ($items) => $items->where('deal_status', 'deal')], 'subtotal')
                    ->with(['opportunityItems' => fn ($items) => $items
                        ->where('opportunity_items.deal_status', 'deal')
                        ->select(
                            'opportunity_items.id',
                            'opportunity_items.opportunity_id',
                            'opportunity_items.product_name',
                        )]),
            ])
            ->latest()
            ->paginate(15, ['*'], 'conversion_page')
            ->withQueryString();

        $opps = Opportunity::visibleTo($user);

        return view('reports.index', [
            'period' => $request->input('period', 'all'),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'owners' => User::query()->whereIn('id', Lead::query()->visibleTo($user)->select('owner_id'))->orderBy('name')->get(['id', 'name']),
            'areas' => Area::query()->orderBy('name')->get(['id', 'name']),
            'businessUnits' => app(BusinessUnitResolver::class)->options()->pluck('name'),
            'sourceOptions' => $this->sourceOptions(),
            'sourceStats' => $sourceStats,
            'totalLeads' => $totalLeads,
            'leadsAdds' => $leadsAdds,
            'activeLeads' => $activeLeads,
            'convertedCustomers' => $convertedCustomers,
            'customersWithDeal' => $customersWithDeal,
            'dealItems' => $dealItems,
            'conversionRate' => $conversionRate,
            'dealRate' => $dealRate,
            'previous' => [
                'leads' => $this->countLeads($user, $previousFrom, $previousTo, $request),
                'customers' => $this->countConvertedCustomers($user, $previousFrom, $previousTo, $request),
                'deals' => $this->countDealCustomers($user, $previousFrom, $previousTo, $request),
            ],
            'conversionRows' => $conversionRows,
            'pipelineValue' => (clone $opps)->where('status', 'open')->sum('estimated_value'),
            'wonValue' => (clone $opps)->where('status', 'won')->sum('estimated_value'),
            'wonCount' => (clone $opps)->where('status', 'won')->count(),
            'lostCount' => (clone $opps)->where('status', 'lost')->count(),
            'customers' => Customer::visibleTo($user)->count(),
            'activities' => Activity::visibleTo($user)->whereMonth('occurred_at', now()->month)->count(),
            'overdueTasks' => Task::visibleTo($user)->whereNotIn('status', ['done', 'cancelled'])->where('due_at', '<', now())->count(),
            'byOwner' => Opportunity::visibleTo($user)->with('owner')->selectRaw('owner_id, count(*) as total, sum(estimated_value) as value')->groupBy('owner_id')->get(),
        ]);
    }

    public function exportCsv(Request $request)
    {
        $columns = $this->selectedExportColumns($request);
        $rows = $this->exportLeadQuery($request)->latest()->get();
        return response()->streamDownload(function () use ($rows, $columns) {
            $out = fopen('php://output', 'w');
            // BOM menjaga karakter UTF-8, sedangkan petunjuk separator membuat
            // Excel langsung memecah data ke kolom pada regional Indonesia.
            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=;\r\n");
            fputcsv($out, array_column($columns, 'label'), ';');
            foreach ($rows as $lead) {
                fputcsv($out, array_map(fn ($column) => $column['value']($lead), $columns), ';');
            }
            fclose($out);
        }, 'laporan-crm-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportPdf(Request $request)
    {
        [$from, $to] = $this->periodRange($request);
        $columns = $this->selectedExportColumns($request);
        $rows = $this->exportLeadQuery($request)->latest()->get();
        return response()->view('reports.export-pdf', compact('rows', 'from', 'to', 'columns'));
    }

    private function exportLeadQuery(Request $request): Builder
    {
        [$from, $to] = $this->periodRange($request);

        return Lead::visibleTo($request->user())
            ->with(['owner:id,name', 'area:id,name', 'businessUnit:id,name', 'convertedCustomer:id,customer_id'])
            ->when($request->search, function (Builder $query, string $search) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('company_name', 'like', "%{$search}%")
                        ->orWhere('lead_id', 'like', "%{$search}%")
                        ->orWhere('brand_name', 'like', "%{$search}%")
                        ->orWhere('pic_name', 'like', "%{$search}%")
                        ->orWhereHas('convertedCustomer', fn (Builder $customer) => $customer
                            ->where('customer_id', 'like', "%{$search}%"));
                });
            })
            ->when($from, fn (Builder $query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('created_at', '<=', $to))
            ->when($request->owner_id, fn (Builder $query, $id) => $query->where('owner_id', $id))
            ->when($request->area_id, fn (Builder $query, $id) => $query->where('area_id', $id))
            ->when($request->business_type, fn (Builder $query, $value) => $query->where('business_type', $value))
            ->when($request->source, fn (Builder $query, $value) => $query->where('source', $value))
            ->when($request->lead_status, fn (Builder $query, $value) => $query->where('status', $value))
            ->when($request->conversion_scope === 'incoming', fn (Builder $query) => $query->whereDoesntHave('convertedCustomer'))
            ->when($request->conversion_scope === 'leads_adds', fn (Builder $query) => $query->where('status', 'leads_adds'))
            ->when($request->conversion_scope === 'converted', fn (Builder $query) => $query->whereHas('convertedCustomer'));
    }

    private function selectedExportColumns(Request $request): array
    {
        $available = [
            'lead_id' => ['label' => 'ID', 'value' => fn (Lead $lead) => $lead->convertedCustomer?->customer_id ?? $lead->lead_id],
            'company_name' => ['label' => 'Perusahaan', 'value' => fn (Lead $lead) => $lead->company_name],
            'owner' => ['label' => 'Sales', 'value' => fn (Lead $lead) => $lead->owner?->name ?? '-'],
            'source' => ['label' => 'Sumber Lead', 'value' => fn (Lead $lead) => $this->sourceLabel($lead->source)],
            'status' => ['label' => 'Status', 'value' => fn (Lead $lead) => $lead->statusLabel()],
            'area' => ['label' => 'Area', 'value' => fn (Lead $lead) => $lead->area?->name ?? '-'],
            'business_unit' => ['label' => 'Jenis Customer', 'value' => fn (Lead $lead) => $lead->businessUnit?->name ?? $lead->business_type ?? '-'],
            'created_at' => ['label' => 'Tanggal Masuk', 'value' => fn (Lead $lead) => $lead->created_at?->format('d/m/Y') ?? '-'],
        ];

        $selected = array_values(array_intersect((array) $request->input('columns', []), array_keys($available)));
        if ($selected === []) {
            $selected = array_keys($available);
        }

        return array_map(fn ($key) => $available[$key], $selected);
    }

    private function sourceLabel(?string $source): string
    {
        return match ($source) {
            null, '' => '-',
            'other' => 'Lainnya',
            default => str($source)->replace(['_', '-'], ' ')->title()->toString(),
        };
    }

    private function sourceOptions(): array
    {
        return [
            'website' => 'Website',
            'whatsapp' => 'WhatsApp',
            'referral' => 'Referral',
            'sales_visit' => 'Sales Visit',
            'event' => 'Event',
            'ads' => 'Ads',
            'social_media' => 'Social Media',
            'marketplace' => 'Marketplace',
            'database' => 'Database',
            'telemarketing' => 'Telemarketing',
            'walk_in' => 'Walk In',
            'other' => 'Lainnya',
        ];
    }

    private function countLeads($user, $from, $to, Request $request): int
    {
        return $this->filteredLeadQuery($user, $from, $to, $request)->count();
    }

    private function countConvertedCustomers($user, $from, $to, Request $request): int
    {
        $ids = $this->filteredLeadQuery($user, $from, $to, $request)->select('id');
        return Customer::visibleTo($user)->whereIn('converted_from_lead_id', $ids)->count();
    }

    private function countDealCustomers($user, $from, $to, Request $request): int
    {
        $ids = $this->filteredLeadQuery($user, $from, $to, $request)->select('id');
        return Customer::visibleTo($user)->whereIn('converted_from_lead_id', $ids)->whereHas('opportunityItems', fn ($q) => $q->where('deal_status', 'deal'))->count();
    }

    private function filteredLeadQuery($user, $from, $to, Request $request): Builder
    {
        return Lead::visibleTo($user)
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->when($request->owner_id, fn ($query, $id) => $query->where('owner_id', $id))
            ->when($request->area_id, fn ($query, $id) => $query->where('area_id', $id))
            ->when($request->business_type, fn ($query, $value) => $query->where('business_type', $value))
            ->when($request->source, fn ($query, $value) => $query->where('source', $value))
            ->when($request->lead_status, fn ($query, $value) => $query->where('status', $value));
    }

    private function previousPeriodRange($from, $to): array
    {
        if (!$from || !$to) return [null, null];
        $start = now()->parse($from);
        $days = $start->diffInDays(now()->parse($to)) + 1;
        return [$start->copy()->subDays($days)->toDateString(), $start->copy()->subDay()->toDateString()];
    }

    private function periodRange(Request $request): array
    {
        return match ($request->input('period', 'all')) {
            'today' => [today()->toDateString(), today()->toDateString()],
            'week' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'month' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'last_3_months' => [today()->subMonths(3)->toDateString(), today()->toDateString()],
            'year' => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            'custom' => [$request->date_from ?: null, $request->date_to ?: null],
            default => [null, null],
        };
    }
}
