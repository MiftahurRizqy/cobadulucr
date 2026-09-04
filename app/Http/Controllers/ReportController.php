<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Activity;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\User;
use App\Support\BusinessUnitResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __invoke(Request $request)
    {
        // Ringkasan performa sales sudah dipusatkan di KPI. Menu laporan kini
        // khusus untuk analisis konversi Customer & Lead.
        $request->merge(['view' => 'conversion']);
        $user = $request->user();
        [$dateFrom, $dateTo] = $this->periodRange($request);
        $periodLabel = Carbon::parse($dateFrom)->isSameDay(Carbon::parse($dateFrom)->startOfMonth())
            && Carbon::parse($dateTo)->isSameDay(Carbon::parse($dateTo)->endOfMonth())
            && Carbon::parse($dateFrom)->isSameMonth(Carbon::parse($dateTo))
                ? Carbon::parse($dateFrom)->translatedFormat('F Y')
                : Carbon::parse($dateFrom)->translatedFormat('d M Y').' – '.Carbon::parse($dateTo)->translatedFormat('d M Y');

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
            ->when($request->lead_status, fn (Builder $query) => $query->withReportStatus($request->lead_status))
            ->when($request->conversion_scope === 'leads_adds', fn (Builder $query) => $query->withReportStatus('leads_adds'))
            ->when($request->conversion_scope === 'incoming', fn (Builder $query) => $query->whereDoesntHave('convertedCustomer'))
            ->when($request->conversion_scope === 'converted', fn (Builder $query) => $query->whereHas('convertedCustomer'))
            ->when($request->conversion_scope === 'deal', fn (Builder $query) => $query->whereHas(
                'convertedCustomer.opportunityItems',
                fn (Builder $items) => $items->where('deal_status', 'deal')
            ));

        $sourceStats = (clone $leadQuery)
            ->leftJoin('customers', 'customers.converted_from_lead_id', '=', 'leads.id')
            ->leftJoin('opportunities', 'opportunities.customer_id', '=', 'customers.id')
            ->leftJoin('opportunity_items', 'opportunity_items.opportunity_id', '=', 'opportunities.id')
            // Hostinger menjalankan MySQL dengan ONLY_FULL_GROUP_BY. Kelompokkan
            // berdasarkan kolom asalnya agar ekspresi label tetap valid di mode itu.
            ->selectRaw("CASE WHEN leads.source IS NULL OR leads.source = '' THEN 'other' ELSE leads.source END as source_key")
            ->selectRaw('COUNT(DISTINCT leads.id) as total_leads')
            ->selectRaw('COUNT(DISTINCT customers.id) as converted_customers')
            ->selectRaw("COUNT(DISTINCT CASE WHEN opportunity_items.deal_status = 'deal' THEN customers.id END) as customers_with_deal")
            ->selectRaw("COUNT(DISTINCT CASE WHEN opportunity_items.deal_status = 'deal' THEN opportunity_items.id END) as deal_items")
            ->groupBy('leads.source')
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
        $leadsAdds = (clone $leadQuery)->withReportStatus('leads_adds')->count();
        $activeLeads = (clone $leadQuery)->whereDoesntHave('convertedCustomer')->count();
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
        $salesMonth = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('sales_month'))
            ? (string) $request->input('sales_month')
            : now()->format('Y-m');
        $salesFrom = Carbon::createFromFormat('Y-m', $salesMonth)->startOfMonth();
        $salesTo = $salesFrom->copy()->endOfMonth();
        $wonOpportunities = (clone $opps)
            ->where('status', 'won')
            ->whereBetween('stage_entered_at', [$salesFrom->copy()->startOfDay(), $salesTo->copy()->endOfDay()])
            ->with('owner:id,name')
            ->withSum('items', 'subtotal')
            ->get();
        $wonValue = $wonOpportunities->sum(fn (Opportunity $opportunity) => $opportunity->realizedValue());
        $byOwner = $wonOpportunities->groupBy('owner_id')->map(function ($opportunities) {
            return (object) [
                'owner_id' => $opportunities->first()->owner_id,
                'owner' => $opportunities->first()->owner,
                'total' => $opportunities->count(),
                'value' => $opportunities->sum(fn (Opportunity $opportunity) => $opportunity->realizedValue()),
            ];
        })->values();

        return view('reports.index', [
            'period' => $request->input('period', 'all'),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'periodLabel' => $periodLabel,
            'owners' => $this->reportOwners($user),
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
            'salesMonth' => $salesMonth,
            'salesFrom' => $salesFrom,
            'wonValue' => $wonValue,
            'wonCount' => $wonOpportunities->count(),
            'lostCount' => (clone $opps)->where('status', 'lost')->whereBetween('stage_entered_at', [$salesFrom->copy()->startOfDay(), $salesTo->copy()->endOfDay()])->count(),
            'customers' => Customer::visibleTo($user)->count(),
            'activities' => Activity::visibleTo($user)->whereMonth('occurred_at', now()->month)->count(),
            'overdueTasks' => Task::visibleTo($user)->whereNotIn('status', ['done', 'cancelled'])->where('due_at', '<', now())->count(),
            'byOwner' => $byOwner,
        ]);
    }

    public function exportCsv(Request $request)
    {
        $columns = $this->selectedExportColumns($request);
        $rows = $this->exportLeadQuery($request)->latest()->get();
        $summary = $this->exportSummary($request);
        [$from, $to] = $this->periodRange($request);
        $reportCompany = app(\App\Services\TenantManager::class)->current();
        $exportedAt = now()->format('d M Y, H:i T');
        AuditLog::record('exported', 'reports', new Lead, newValues: ['format' => 'csv', 'date_from' => (string) $from, 'date_to' => (string) $to, 'records' => $rows->count()]);
        return response()->streamDownload(function () use ($rows, $columns, $summary, $from, $to, $reportCompany, $exportedAt) {
            $out = fopen('php://output', 'w');
            // BOM menjaga karakter UTF-8, sedangkan petunjuk separator membuat
            // Excel langsung memecah data ke kolom pada regional Indonesia.
            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=;\r\n");
            fputcsv($out, ['Laporan Leads'], ';');
            fputcsv($out, ['Perusahaan', $reportCompany?->name ?? 'CRM'], ';');
            fputcsv($out, ['Periode', $from, $to], ';');
            fputcsv($out, ['Diekspor pada', $exportedAt], ';');
            foreach ($summary as $label => $value) {
                fputcsv($out, [$label, $value], ';');
            }
            fputcsv($out, ['Ringkasan mengikuti periode dan filter utama; status hanya menyaring detail.'], ';');
            fputcsv($out, [], ';');
            fputcsv($out, array_column($columns, 'label'), ';');
            foreach ($rows as $lead) {
                fputcsv($out, array_map(fn ($column) => $column['value']($lead), $columns), ';');
            }
            fclose($out);
        }, 'laporan-crm-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportExcel(Request $request)
    {
        $columns = $this->selectedExportColumns($request);
        $rows = $this->exportLeadQuery($request)->latest()->get();
        $summary = $this->exportSummary($request);
        [$from, $to] = $this->periodRange($request);
        $reportCompany = app(\App\Services\TenantManager::class)->current();
        $companyName = $reportCompany?->name ?: config('app.name', 'CRM');
        $exportedAt = now()->translatedFormat('d F Y, H:i').' WIB';

        AuditLog::record('exported', 'reports', new Lead, newValues: [
            'format' => 'xlsx', 'date_from' => (string) $from, 'date_to' => (string) $to, 'records' => $rows->count(),
        ]);

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Laporan Leads');
        $book->getDefaultStyle()->getFont()->setName('Aptos')->setSize(10)->getColor()->setRGB('172033');
        $sheet->setShowGridlines(false);
        $sheet->getSheetView()->setZoomScale(85);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(.45)->setRight(.3)->setBottom(.55)->setLeft(.3);
        $footerCompany = str_replace('&', '&&', $companyName);
        $sheet->getHeaderFooter()->setOddFooter('&L'.$footerCompany.' - Dokumen internal&C&P / &N');

        $sheet->mergeCells('A1:B3');
        $sheet->mergeCells('C1:J1')->setCellValue('C1', $companyName);
        $sheet->mergeCells('C2:J2')->setCellValue('C2', 'Laporan Leads');
        $periodText = Carbon::parse($from)->translatedFormat('d M Y').' - '.Carbon::parse($to)->translatedFormat('d M Y');
        $sheet->mergeCells('C3:J3')->setCellValue('C3', 'Periode '.$periodText.'  |  Diterbitkan '.$exportedAt);
        $sheet->getStyle('A1:J3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('C1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('C2')->getFont()->setBold(true)->setSize(19);
        $sheet->getStyle('C3')->getFont()->setSize(8)->getColor()->setRGB('667085');
        $sheet->getStyle('A3:J3')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('252B36');
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(27);
        $sheet->getRowDimension(3)->setRowHeight(23);
        $sheet->getRowDimension(4)->setRowHeight(8);

        $logoPath = $reportCompany?->logo_path ? public_path('storage/'.ltrim($reportCompany->logo_path, '/')) : null;
        if ($logoPath && is_file($logoPath)) {
            try {
                $logo = new Drawing;
                $logo->setName('Logo '.$companyName);
                $logo->setPath($logoPath);
                $logo->setCoordinates('A1');
                $logo->setHeight(54);
                $logo->setOffsetX(5);
                $logo->setOffsetY(4);
                $logo->setWorksheet($sheet);
            } catch (\Throwable) {
                $sheet->setCellValue('A1', mb_strtoupper(mb_substr($companyName, 0, 3)));
            }
        } else {
            $sheet->setCellValue('A1', mb_strtoupper(mb_substr($companyName, 0, 3)));
        }
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('312E81');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $summaryItems = array_values($summary);
        $summaryLabels = array_keys($summary);
        foreach ([['A','B'], ['C','D'], ['E','F'], ['G','J']] as $index => [$start, $end]) {
            $sheet->mergeCells("{$start}5:{$end}5")->mergeCells("{$start}6:{$end}7");
            $sheet->setCellValue($start.'5', mb_strtoupper($summaryLabels[$index] ?? ''));
            $value = $summaryItems[$index] ?? 0;
            $isPercent = is_string($value) && str_ends_with($value, '%');
            $sheet->setCellValue($start.'6', $isPercent ? ((float) rtrim($value, '%')) / 100 : (int) $value);
            $cardRange = "{$start}5:{$end}7";
            $sheet->getStyle($cardRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            $sheet->getStyle($cardRange)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9E0EA');
            $sheet->getStyle($cardRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle($start.'5')->getFont()->setBold(true)->setSize(8)->getColor()->setRGB('667085');
            $sheet->getStyle($start.'6')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB($index === 1 ? '047857' : ($index === 3 ? '312E81' : '172033'));
            $sheet->getStyle($start.'6')->getNumberFormat()->setFormatCode($isPercent ? '0.0%' : '#,##0');
        }

        $detailRow = 10;
        $columnCount = count($columns);
        $detailLastColumn = Coordinate::stringFromColumnIndex(max(1, $columnCount));
        $sheet->mergeCells("A9:{$detailLastColumn}9")->setCellValue('A9', 'Detail leads · '.$rows->count().' data');
        $sheet->getStyle("A9:{$detailLastColumn}9")->getFont()->setBold(true)->setSize(11);
        $sheet->fromArray(array_column($columns, 'label'), null, 'A'.$detailRow);
        $sheet->getStyle("A{$detailRow}:{$detailLastColumn}{$detailRow}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$detailRow}:{$detailLastColumn}{$detailRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('252B36');
        $sheet->getStyle("A{$detailRow}:{$detailLastColumn}{$detailRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($detailRow)->setRowHeight(28);

        $rowNumber = $detailRow + 1;
        foreach ($rows as $lead) {
            foreach ($columns as $columnIndex => $column) {
                $cell = Coordinate::stringFromColumnIndex($columnIndex + 1).$rowNumber;
                if ($column['key'] === 'created_at' && $lead->created_at) {
                    $sheet->setCellValue($cell, ExcelDate::PHPToExcel($lead->created_at));
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('dd mmm yyyy, hh:mm');
                } else {
                    $sheet->setCellValue($cell, $column['value']($lead));
                }
            }
            if ($rowNumber % 2 === 0) {
                $sheet->getStyle("A{$rowNumber}:{$detailLastColumn}{$rowNumber}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }
            $sheet->getRowDimension($rowNumber)->setRowHeight(25);
            $rowNumber++;
        }
        if ($rows->isEmpty()) {
            $sheet->mergeCells("A{$rowNumber}:{$detailLastColumn}{$rowNumber}")->setCellValue('A'.$rowNumber, 'Tidak ada lead sesuai filter.');
            $rowNumber++;
        }

        $lastRow = $rowNumber - 1;
        $sheet->getStyle("A{$detailRow}:{$detailLastColumn}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9E0EA');
        $sheet->getStyle("A".($detailRow + 1).":{$detailLastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $widths = ['lead_id'=>19, 'company_name'=>27, 'brand_name'=>21, 'owner'=>22, 'source'=>16, 'status'=>18, 'customer_status'=>24, 'area'=>17, 'business_unit'=>23, 'created_at'=>21];
        foreach ($columns as $columnIndex => $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex + 1))->setWidth($widths[$column['key']] ?? 18);
        }
        $sheet->freezePane('A11');
        $sheet->setAutoFilter("A{$detailRow}:{$detailLastColumn}{$lastRow}");
        $sheet->getPageSetup()->setPrintArea("A1:J{$lastRow}")->setRowsToRepeatAtTopByStartAndEnd($detailRow, $detailRow);
        $sheet->getPageSetup()->setHorizontalCentered(true);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, 'laporan-leads-'.now()->format('Ymd-His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function exportPdf(Request $request)
    {
        [$from, $to] = $this->periodRange($request);
        $columns = $this->selectedExportColumns($request);
        $rows = $this->exportLeadQuery($request)->latest()->get();
        $summary = $this->exportSummary($request);
        $reportCompany = app(\App\Services\TenantManager::class)->current();
        $exportedAt = now()->format('d M Y, H:i T');
        AuditLog::record('exported', 'reports', new Lead, newValues: ['format' => 'pdf', 'date_from' => (string) $from, 'date_to' => (string) $to, 'records' => $rows->count()]);
        return response()->view('reports.export-pdf', compact('rows', 'from', 'to', 'columns', 'summary', 'reportCompany', 'exportedAt'));
    }

    private function exportSummary(Request $request): array
    {
        $summaryRequest = $request->duplicate($request->except(['lead_status', 'conversion_scope']));
        $query = $this->exportLeadQuery($summaryRequest);
        $total = (clone $query)->count();
        $converted = Customer::visibleTo($request->user())
            ->whereIn('converted_from_lead_id', (clone $query)->select('id'))->count();

        return [
            'Lead Masuk' => $total,
            'Menjadi customer' => $converted,
            'Belum menjadi customer' => (clone $query)->whereDoesntHave('convertedCustomer')->count(),
            'Konversi' => ($total ? round($converted / $total * 100, 1) : 0).'%',
        ];
    }

    private function reportOwners(User $user)
    {
        return User::query()->where(function (Builder $query) use ($user) {
            // Keep historical owners selectable, including inactive accounts.
            $query->whereIn('id', Lead::visibleTo($user)->select('owner_id'))
                ->orWhere(function (Builder $eligible) use ($user) {
                    $eligible->where('is_active', true)
                        ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('slug', ['sales', 'telesales']));
                    if ($user->isMasterAdmin() || $user->hasRole('csa')) return;
                    if ($user->authority_level === 'manager') {
                        $eligible->whereHas('businessUnits', fn (Builder $units) => $units->whereIn('business_units.id', $user->businessUnits()->pluck('business_units.id')));
                    } elseif ($user->authority_level === 'supervisor') {
                        $eligible->where('manager_id', $user->id);
                    } else {
                        $eligible->whereKey($user->id);
                    }
                });
        })->orderBy('name')->get(['id', 'name']);
    }

    private function exportLeadQuery(Request $request): Builder
    {
        [$from, $to] = $this->periodRange($request);

        return Lead::visibleTo($request->user())
            ->with(['owner:id,name', 'area:id,name', 'businessUnit:id,name', 'convertedCustomer:id,customer_id,converted_from_lead_id'])
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
            ->when($request->lead_status, fn (Builder $query) => $query->withReportStatus($request->lead_status))
            ->when($request->conversion_scope === 'leads_adds', fn (Builder $query) => $query->withReportStatus('leads_adds'))
            ->when($request->conversion_scope === 'incoming', fn (Builder $query) => $query->whereDoesntHave('convertedCustomer'))
            ->when($request->conversion_scope === 'converted', fn (Builder $query) => $query->whereHas('convertedCustomer'));
    }

    private function selectedExportColumns(Request $request): array
    {
        $available = [
            'lead_id' => ['label' => 'ID', 'value' => fn (Lead $lead) => $lead->convertedCustomer?->customer_id ?? $lead->lead_id],
            'company_name' => ['label' => 'Perusahaan', 'value' => fn (Lead $lead) => $lead->company_name],
            'brand_name' => ['label' => 'Brand', 'value' => fn (Lead $lead) => $lead->brand_name ?: '-'],
            'owner' => ['label' => 'Sales / Telesales', 'value' => fn (Lead $lead) => $lead->owner?->name ?? '-'],
            'source' => ['label' => 'Sumber Lead', 'value' => fn (Lead $lead) => $this->sourceLabel($lead->source)],
            'status' => ['label' => 'Status Lead', 'value' => fn (Lead $lead) => $lead->reportStatusLabel()],
            'customer_status' => ['label' => 'Status Customer', 'value' => fn (Lead $lead) => $lead->convertedCustomer ? 'Sudah menjadi customer' : 'Belum menjadi customer'],
            'area' => ['label' => 'Area', 'value' => fn (Lead $lead) => $lead->area?->name ?? '-'],
            'business_unit' => ['label' => 'Jenis Customer', 'value' => fn (Lead $lead) => $lead->businessUnit?->name ?? $lead->business_type ?? '-'],
            'created_at' => ['label' => 'Tanggal Masuk', 'value' => fn (Lead $lead) => $lead->created_at?->format('d/m/Y H:i') ?? '-'],
        ];

        $selected = array_values(array_intersect((array) $request->input('columns', []), array_keys($available)));
        if ($selected === []) {
            $selected = array_keys($available);
        }

        return array_map(fn ($key) => ['key' => $key] + $available[$key], $selected);
    }

    private function sourceLabel(?string $source): string
    {
        return match ($source) {
            null, '' => '-',
            'website' => 'Website',
            'whatsapp_ads' => 'WhatsApp / Ads',
            'sales_visit' => 'Sales Visit',
            'social_media' => 'Social Media',
            'other' => 'Lainnya',
            // Source kustom disimpan sebagai teks akhir, jadi tampilkan persis
            // seperti yang diketik pengguna pada laporan dan export.
            default => $source,
        };
    }

    private function sourceOptions(): array
    {
        $options = [
            'website' => 'Website',
            'whatsapp_ads' => 'WhatsApp / Ads',
            'sales_visit' => 'Sales Visit',
            'social_media' => 'Social Media',
        ];

        // Source yang dibuat melalui pilihan "Lainnya" disimpan per perusahaan
        // dan otomatis menjadi pilihan filter maupun export berikutnya.
        foreach (SystemSetting::json('lead_source_options') as $source) {
            $source = trim((string) $source);
            if ($source !== '') {
                $options[$source] = $source;
            }
        }

        $options['other'] = 'Lainnya';

        return $options;
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
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('start_date'))
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('end_date'))) {
            try {
                $from = Carbon::createFromFormat('Y-m-d', (string) $request->input('start_date'))->startOfDay();
                $to = Carbon::createFromFormat('Y-m-d', (string) $request->input('end_date'))->endOfDay();

                if ($from->lte($to)) {
                    return [$from->toDateString(), $to->toDateString()];
                }
            } catch (\Throwable) {
                // Gunakan periode bulan berjalan jika tanggal tidak valid.
            }
        }

        if (preg_match('/^\d{4}-\d{2}$/', (string) $request->input('report_month'))) {
            $month = Carbon::createFromFormat('Y-m', (string) $request->input('report_month'));

            return [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()];
        }

        return match ($request->input('period', 'all')) {
            'today' => [today()->toDateString(), today()->toDateString()],
            'week' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'month' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'last_3_months' => [today()->subMonths(3)->toDateString(), today()->toDateString()],
            'year' => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            'custom' => [$request->date_from ?: null, $request->date_to ?: null],
            default => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }
}
