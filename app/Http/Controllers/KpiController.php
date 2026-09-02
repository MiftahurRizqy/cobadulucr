<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\Customer;
use App\Models\KpiTemplate;
use App\Models\SalesKpiTarget;
use App\Models\SystemSetting;
use App\Services\KpiMetricConfig;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class KpiController extends Controller
{
    public function index(Request $request)
    {
        $data = $this->reportData($request);
        $data['templates'] = KpiTemplate::query()->whereIn('role_slug', ['sales', 'telesales'])->get()->keyBy('role_slug');
        $data['canManageTemplates'] = $request->user()->canAccess('kpi.manage') && ! $request->user()->isSales();
        $data['operationalKpiEnabled'] = SystemSetting::bool('operational_kpi_enabled', true);

        return view('kpi.index', $data);
    }

    public function exportExcel(Request $request)
    {
        $data = $this->reportData($request);
        $reportCompany = app(\App\Services\TenantManager::class)->current();
        $companyName = $reportCompany?->name ?: config('app.name', 'CRM');
        $reportCode = 'KPI/'.$data['from']->format('Ym');
        AuditLog::record('exported', 'kpi', new SalesKpiTarget, newValues: ['format' => 'xlsx', 'period' => $data['periodLabel']]);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('KPI '.$data['from']->format('Y-m'));
        $book->getDefaultStyle()->getFont()->setName('Aptos')->setSize(10)->getColor()->setRGB('0F172A');
        $sheet->setShowGridlines(false);
        $sheet->getSheetView()->setZoomScale(85);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(.45)->setRight(.3)->setBottom(.55)->setLeft(.3);
        $footerCompany = str_replace('&', '&&', $companyName);
        $sheet->getHeaderFooter()->setOddFooter('&L'.$footerCompany.' - Dokumen internal&C&P / &N&R'.$reportCode);
        $sheet->freezePane('A11');
        $sheet->mergeCells('A1:B3');
        $sheet->mergeCells('C1:I1')->setCellValue('C1', $companyName);
        $sheet->mergeCells('C2:I2')->setCellValue('C2', 'Laporan KPI Penjualan');
        $sheet->mergeCells('C3:I3')->setCellValue('C3', 'Periode '.$data['periodLabel'].'  |  Diterbitkan '.now()->translatedFormat('d F Y, H:i').' WIB  |  '.$reportCode);
        $sheet->getStyle('A1:I3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('C1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('0F172A');
        $sheet->getStyle('C2')->getFont()->setBold(true)->setSize(19)->getColor()->setRGB('0F172A');
        $sheet->getStyle('C3')->getFont()->setSize(8)->getColor()->setRGB('64748B');
        $sheet->getStyle('A3:I3')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('312E81');
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(27);
        $sheet->getRowDimension(3)->setRowHeight(23);
        $sheet->getRowDimension(4)->setRowHeight(8);

        $logoPath = $reportCompany?->logo_path
            ? public_path('storage/'.ltrim($reportCompany->logo_path, '/'))
            : null;
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

        $metrics = [
            ['A5:B5', 'A6:B7', 'TARGET TIM', $data['summary']->target, 'currency'],
            ['C5:D5', 'C6:D7', 'REALISASI TIM', $data['summary']->realization, 'realization'],
            ['E5:F5', 'E6:F7', 'PENCAPAIAN', $data['summary']->achievement / 100, 'percent'],
            ['G5:I5', 'G6:I7', 'OPPORTUNITY CLOSED WON', $data['summary']->deals, 'integer'],
        ];
        foreach ($metrics as [$labelRange, $valueRange, $label, $value, $type]) {
            $sheet->mergeCells($labelRange)->mergeCells($valueRange);
            $labelCell = explode(':', $labelRange)[0];
            $valueCell = explode(':', $valueRange)[0];
            $sheet->setCellValue($labelCell, $label)->setCellValue($valueCell, $value);
            $sheet->getStyle($labelRange)->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('64748B');
            $sheet->getStyle($valueRange)->getFont()->setBold(true)->setSize(15)->getColor()->setRGB($type === 'realization' ? '047857' : ($type === 'percent' ? '312E81' : '0F172A'));
            $metricRange = explode(':', $labelRange)[0].':'.explode(':', $valueRange)[1];
            $sheet->getStyle($metricRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            $sheet->getStyle($metricRange)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
            $sheet->getStyle($metricRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        }
        $sheet->getRowDimension(5)->setRowHeight(20);
        $sheet->getRowDimension(6)->setRowHeight(24);
        $sheet->getRowDimension(7)->setRowHeight(18);
        $sheet->getStyle('A6')->getNumberFormat()->setFormatCode('"Rp" #,##0');
        $sheet->getStyle('C6')->getNumberFormat()->setFormatCode('"Rp" #,##0');
        $sheet->getStyle('E6')->getNumberFormat()->setFormatCode('#,##0.0%');
        $sheet->getStyle('G6')->getNumberFormat()->setFormatCode('#,##0 "opportunity"');

        $headers = ['Head / Sales', 'Keterangan', 'Target', 'Realisasi', 'Won', 'Closing Rate', 'Pencapaian', 'KPI Operasional (Realisasi / Target)', 'Catatan Head / Manager'];
        $sheet->fromArray($headers, null, 'A10');
        $sheet->getStyle('A10:I10')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A10:I10')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('312E81');
        $sheet->getStyle('A10:I10')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension(10)->setRowHeight(28);

        $rowNumber = 11;
        foreach ($data['displayRows'] as $displayRow) {
            if ($displayRow->type === 'head') {
                $headRow = $displayRow->data;
                $sheet->fromArray([$headRow->head->name, 'Head / Koordinator', $headRow->target, $headRow->realization, $headRow->deals, $headRow->closingRate / 100, $headRow->achievement / 100, '—', '-'], null, 'A'.$rowNumber);
                $sheet->getStyle("A{$rowNumber}:I{$rowNumber}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E7FF');
                $sheet->getStyle("A{$rowNumber}:I{$rowNumber}")->getFont()->setBold(true)->getColor()->setRGB('312E81');
                $sheet->getRowDimension($rowNumber)->setRowHeight(34);
                $rowNumber++;
                continue;
            }
            $row = $displayRow->data;
            $hasComment = filled($row->target?->evaluation_notes);
            $commentTime = $hasComment && $row->target?->updated_at ? $row->target->updated_at->format('d M Y, H:i').' WIB' : null;
            $operational = 'NOO '.number_format($row->noo).'/'.number_format((int) ($row->target?->noo_target ?? 0))
                .'  ·  Custom '.number_format($row->customNoo).'/'.number_format((int) ($row->target?->custom_noo_target ?? 0))
                .'  ·  Akun Besar '.number_format($row->largeAccounts).'/'.number_format((int) ($row->target?->large_account_target ?? 6))."\n"
                .'Drink '.number_format($row->drinkVolume).'/'.number_format((int) ($row->target?->drink_volume_target ?? 0))
                .'  ·  Food '.number_format($row->foodVolume).'/'.number_format((int) ($row->target?->food_volume_target ?? 0)).' pcs';
            $comment = $hasComment
                ? $row->target->evaluation_notes."\nBy ".($row->target?->updater?->name ?? '-').($commentTime ? ' · '.$commentTime : '')
                : '-';
            $roleLabel = $row->sales->roles
                ->first(fn ($role) => in_array($role->slug, ['sales', 'telesales'], true))?->name ?? 'Sales';
            $sheet->fromArray([$row->sales->name, $roleLabel, (float) ($row->target?->sales_target ?? 0), (float) $row->realization, $row->deals, $row->closingRate / 100, $row->achievement / 100, $operational, $comment], null, 'A'.$rowNumber);
            $sheet->getStyle('A'.$rowNumber)->getAlignment()->setIndent(2);
            if ($rowNumber % 2 === 0) $sheet->getStyle("A{$rowNumber}:I{$rowNumber}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            $sheet->getRowDimension($rowNumber)->setRowHeight(42);
            $rowNumber++;
        }
        $lastRow = max(11, $rowNumber - 1);
        $sheet->getStyle("A10:I{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
        $sheet->getStyle("A11:I{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("C11:D{$lastRow}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
        $sheet->getStyle("E11:E{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("F11:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.0%');
        $sheet->getStyle("H11:I{$lastRow}")->getAlignment()->setWrapText(true);
        foreach (['A'=>20,'B'=>14,'C'=>14,'D'=>15,'E'=>7,'F'=>10,'G'=>11,'H'=>27,'I'=>26] as $column => $width) $sheet->getColumnDimension($column)->setWidth($width);
        $sheet->setAutoFilter("A10:I{$lastRow}");
        $sheet->getPageSetup()->setPrintArea("A1:I{$lastRow}")->setRowsToRepeatAtTopByStartAndEnd(10, 10);
        $sheet->getPageSetup()->setHorizontalCentered(true);

        $book->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, 'laporan-kpi-penjualan-'.$data['exportKey'].'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->reportData($request);
        $data['reportCompany'] = app(\App\Services\TenantManager::class)->current();
        $data['exportedAt'] = now()->translatedFormat('d F Y, H:i').' WIB';
        AuditLog::record('exported', 'kpi', new SalesKpiTarget, newValues: ['format' => 'pdf', 'period' => $data['periodLabel']]);
        return Pdf::loadView('kpi.export-pdf', $data)->setPaper('a4', 'portrait')
            ->download('laporan-kpi-penjualan-'.$data['exportKey'].'.pdf');
    }

    private function reportData(Request $request): array
    {
        [$from, $to] = $this->period($request);
        $users = $this->visibleSales($request->user());
        $targets = SalesKpiTarget::with('updater:id,name')
            ->whereIn('user_id', $users->pluck('id'))
            ->whereDate('period_start', '<=', $to)
            ->whereDate('period_end', '>=', $from)
            ->orderBy('period_start')->get()->groupBy('user_id');
        $operationalKpiEnabled = SystemSetting::bool('operational_kpi_enabled', true);
        $kpiMetrics = app(KpiMetricConfig::class)->all();
        $rows = $users->map(function (User $sales) use ($from, $to, $targets, $request, $operationalKpiEnabled, $kpiMetrics) {
            $wonOpportunities = Opportunity::query()
                ->with(['items', 'customer'])
                ->withSum('items', 'subtotal')
                ->where('owner_id', $sales->id)
                ->where('status', 'won')
                ->whereBetween('stage_entered_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->get();
            $opportunityCount = Opportunity::query()->where('owner_id', $sales->id)
                ->where(function ($query) use ($from, $to) {
                    $query->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                        ->orWhere(fn ($query) => $query->where('status', 'won')->whereBetween('stage_entered_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]));
                })->count();
            $deals = $wonOpportunities->count();
            $salesTargets = $targets->get($sales->id, collect());
            $target = $salesTargets->last();
            if ($target) {
                foreach (['sales_target', 'noo_target', 'custom_noo_target', 'large_account_target', 'drink_volume_target', 'food_volume_target'] as $field) {
                    $target->{$field} = $salesTargets->sum(fn ($item) => (float) $item->{$field});
                }
            }
            $realization = $wonOpportunities->sum(fn (Opportunity $opportunity) => $opportunity->realizedValue());
            $newCustomers = Customer::query()->where('sales_owner_id', $sales->id)->whereBetween('became_customer_at', [$from, $to]);
            $noo = (clone $newCustomers)->count();
            $nooCustomers = (clone $newCustomers)->orderBy('company_name')->get(['id', 'company_name', 'became_customer_at']);
            // Satu customer baru dihitung sekali sebagai Custom NOO apabila ia
            // memiliki minimal satu item Custom, terlepas dari pipeline salesnya.
            $customNoo = (clone $newCustomers)->whereHas(
                'opportunityItems',
                fn ($query) => $query->where('product_type', 'custom')
            )->count();
            $customNooCustomers = (clone $newCustomers)->whereHas(
                'opportunityItems',
                fn ($query) => $query->where('product_type', 'custom')
            )->orderBy('company_name')->get(['id', 'company_name', 'became_customer_at']);
            // Satu customer dihitung sekali dalam periode ini apabila memiliki
            // minimal satu Closed Won dengan nilai realisasi Rp50 juta atau lebih.
            $largeAccountThreshold = (int) $kpiMetrics['large_account']['threshold'];
            $largeAccounts = $wonOpportunities
                ->filter(fn (Opportunity $opportunity) => $opportunity->realizedValue() >= $largeAccountThreshold)
                ->pluck('customer_id')
                ->unique()
                ->count();
            $largeAccountCustomers = $wonOpportunities
                ->filter(fn (Opportunity $opportunity) => $opportunity->realizedValue() >= $largeAccountThreshold)
                ->groupBy('customer_id')
                ->map(function ($opportunities) {
                    $opportunity = $opportunities->first();

                    return [
                        'id' => $opportunity->customer_id,
                        'name' => $opportunity->customer?->company_name ?? 'Customer tidak ditemukan',
                        'value' => $opportunities->sum(fn (Opportunity $item) => $item->realizedValue()),
                    ];
                })->values();
            $wonItems = $wonOpportunities->flatMap->items;
            $drinkVolume = $wonItems->where('market_segment', 'drink')->sum('quantity');
            $foodVolume = $wonItems->where('market_segment', 'food')->sum('quantity');

            $achievement = $operationalKpiEnabled
                ? $this->operationalAchievement($this->enabledMetricValues($kpiMetrics, $noo, (int) ($target?->noo_target ?? 0), $customNoo, (int) ($target?->custom_noo_target ?? 0), $largeAccounts, (int) ($target?->large_account_target ?? 6), $drinkVolume, (int) ($target?->drink_volume_target ?? 0), $foodVolume, (int) ($target?->food_volume_target ?? 0)))
                : ($target && (float) $target->sales_target > 0 ? round($realization / (float) $target->sales_target * 100, 1) : 0);

            return (object) (compact('sales', 'target', 'realization', 'deals', 'opportunityCount', 'noo', 'nooCustomers', 'customNoo', 'customNooCustomers', 'largeAccounts', 'largeAccountCustomers', 'drinkVolume', 'foodVolume', 'achievement') + [
                'closingRate' => $opportunityCount > 0 ? round($deals / $opportunityCount * 100, 1) : 0,
                'canManage' => $this->canManage($request->user(), $sales),
            ]);
        });

        $summary = (object) [
            'target' => $rows->sum(fn ($row) => (float) ($row->target?->sales_target ?? 0)),
            'realization' => $rows->sum('realization'),
            'deals' => $rows->sum('deals'),
            'achievement' => 0,
            'noo' => $rows->sum('noo'), 'customNoo' => $rows->sum('customNoo'), 'largeAccounts' => $rows->sum('largeAccounts'),
            'drinkVolume' => $rows->sum('drinkVolume'), 'foodVolume' => $rows->sum('foodVolume'),
            'nooTarget' => $rows->sum(fn ($row) => (int) ($row->target?->noo_target ?? 0)),
            'customNooTarget' => $rows->sum(fn ($row) => (int) ($row->target?->custom_noo_target ?? 0)),
            'largeAccountTarget' => $rows->sum(fn ($row) => (int) ($row->target?->large_account_target ?? 6)),
            'drinkVolumeTarget' => $rows->sum(fn ($row) => (int) ($row->target?->drink_volume_target ?? 0)),
            'foodVolumeTarget' => $rows->sum(fn ($row) => (int) ($row->target?->food_volume_target ?? 0)),
        ];
        $summary->achievement = $operationalKpiEnabled
            ? $this->operationalAchievement($this->enabledMetricValues($kpiMetrics, $summary->noo, $summary->nooTarget, $summary->customNoo, $summary->customNooTarget, $summary->largeAccounts, $summary->largeAccountTarget, $summary->drinkVolume, $summary->drinkVolumeTarget, $summary->foodVolume, $summary->foodVolumeTarget))
            : ($summary->target > 0 ? round($summary->realization / $summary->target * 100, 1) : 0);

        $headRows = $request->user()->isSales()
            ? collect()
            : $rows->filter(fn ($row) => $row->sales->manager)->groupBy(fn ($row) => $row->sales->manager_id)
                ->map(function ($salesRows) use ($operationalKpiEnabled, $kpiMetrics) {
                    $target = $salesRows->sum(fn ($row) => (float) ($row->target?->sales_target ?? 0));
                    $realization = $salesRows->sum('realization');
                    $deals = $salesRows->sum('deals');
                    $opportunityCount = $salesRows->sum('opportunityCount');

                    $noo = $salesRows->sum('noo');
                    $customNoo = $salesRows->sum('customNoo');
                    $largeAccounts = $salesRows->sum('largeAccounts');
                    $drinkVolume = $salesRows->sum('drinkVolume');
                    $foodVolume = $salesRows->sum('foodVolume');
                    $nooTarget = $salesRows->sum(fn ($row) => (int) ($row->target?->noo_target ?? 0));
                    $customNooTarget = $salesRows->sum(fn ($row) => (int) ($row->target?->custom_noo_target ?? 0));
                    $largeAccountTarget = $salesRows->sum(fn ($row) => (int) ($row->target?->large_account_target ?? 6));
                    $drinkVolumeTarget = $salesRows->sum(fn ($row) => (int) ($row->target?->drink_volume_target ?? 0));
                    $foodVolumeTarget = $salesRows->sum(fn ($row) => (int) ($row->target?->food_volume_target ?? 0));

                    return (object) [
                        'head' => $salesRows->first()->sales->manager,
                        'salesCount' => $salesRows->count(),
                        'target' => $target,
                        'realization' => $realization,
                        'deals' => $deals,
                        'closingRate' => $opportunityCount > 0 ? round($deals / $opportunityCount * 100, 1) : 0,
                        'achievement' => $operationalKpiEnabled ? $this->operationalAchievement($this->enabledMetricValues($kpiMetrics, $noo, $nooTarget, $customNoo, $customNooTarget, $largeAccounts, $largeAccountTarget, $drinkVolume, $drinkVolumeTarget, $foodVolume, $foodVolumeTarget)) : ($target > 0 ? round($realization / $target * 100, 1) : 0),
                        'noo' => $noo, 'customNoo' => $customNoo, 'largeAccounts' => $largeAccounts,
                        'drinkVolume' => $drinkVolume, 'foodVolume' => $foodVolume,
                    ];
                })->sortByDesc('realization')->values();

        $displayRows = collect();
        foreach ($headRows as $headRow) {
            $displayRows->push((object) ['type' => 'head', 'data' => $headRow]);
            foreach ($rows->filter(fn ($row) => (int) $row->sales->manager_id === (int) $headRow->head->id)->sortByDesc('realization') as $salesRow) {
                $displayRows->push((object) ['type' => 'sales', 'data' => $salesRow]);
            }
        }
        $groupedSalesIds = $displayRows->where('type', 'sales')->map(fn ($row) => $row->data->sales->id);
        foreach ($rows->whereNotIn('sales.id', $groupedSalesIds)->sortByDesc('realization') as $salesRow) {
            $displayRows->push((object) ['type' => 'sales', 'data' => $salesRow]);
        }

        $isSingleMonth = $from->day === 1 && $to->isSameDay($from->copy()->endOfMonth());
        $periodLabel = $isSingleMonth
            ? $from->copy()->locale('id')->translatedFormat('F Y')
            : $from->copy()->locale('id')->translatedFormat('d M Y').' – '.$to->copy()->locale('id')->translatedFormat('d M Y');
        $exportKey = $isSingleMonth ? $from->format('Y-m') : $from->format('Y-m-d').'_sampai_'.$to->format('Y-m-d');

        return compact('rows', 'headRows', 'displayRows', 'from', 'to', 'summary', 'isSingleMonth', 'periodLabel', 'exportKey', 'kpiMetrics');
    }

    public function update(Request $request, User $sales)
    {
        abort_unless($this->canManage($request->user(), $sales), 403);
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'], 'sales_target' => ['required', 'numeric', 'min:0'],
            'noo_target' => ['nullable', 'integer', 'min:0'], 'custom_noo_target' => ['nullable', 'integer', 'min:0'],
            'large_account_target' => ['nullable', 'integer', 'min:0'],
            'drink_volume_target' => ['nullable', 'integer', 'min:0'],
            'food_volume_target' => ['nullable', 'integer', 'min:0'],
            'evaluation_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $from = Carbon::createFromFormat('!Y-m', $data['period'])->startOfMonth();
        SalesKpiTarget::updateOrCreate(
            ['user_id' => $sales->id, 'period_start' => $from->toDateString(), 'period_end' => $from->copy()->endOfMonth()->toDateString()],
            ['sales_target' => $data['sales_target'], 'noo_target' => $data['noo_target'] ?? 0, 'custom_noo_target' => $data['custom_noo_target'] ?? 0, 'large_account_target' => $data['large_account_target'] ?? 6,
                'drink_volume_target' => $data['drink_volume_target'] ?? 0,
                'food_volume_target' => $data['food_volume_target'] ?? 0, 'evaluation_notes' => $data['evaluation_notes'] ?? null, 'updated_by' => $request->user()->id]
        );
        return back()->with('success', 'Target dan catatan KPI berhasil disimpan.');
    }

    public function updateTemplate(Request $request, string $roleSlug)
    {
        abort_unless(! $request->user()->isSales(), 403);
        $data = $this->validateTargetValues($request);
        KpiTemplate::updateOrCreate(
            ['role_slug' => $this->validateTemplateRole($roleSlug)],
            $data + ['updated_by' => $request->user()->id]
        );

        return back()->with('success', 'Template KPI '.ucfirst($roleSlug).' berhasil disimpan.');
    }

    public function applyTemplate(Request $request, string $roleSlug)
    {
        abort_unless(! $request->user()->isSales(), 403);
        $roleSlug = $this->validateTemplateRole($roleSlug);
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'preserve_existing' => ['nullable', 'boolean'],
        ]);
        $template = KpiTemplate::where('role_slug', $roleSlug)->firstOrFail();
        $from = CarbonImmutable::createFromFormat('!Y-m', $data['period'])->startOfMonth();
        $users = $this->visibleSales($request->user())->filter(fn (User $user) => $user->roles->contains('slug', $roleSlug));
        $applied = 0;

        DB::transaction(function () use ($users, $template, $from, $request, $data, &$applied) {
            foreach ($users as $sales) {
                $target = $this->monthlyTarget($sales->id, $from);
                if ($target->exists && $request->boolean('preserve_existing')) continue;
                $target->fill($template->targetValues() + ['updated_by' => $request->user()->id])->save();
                $applied++;
            }
        });

        return back()->with('success', "Template diterapkan ke {$applied} {$roleSlug} untuk ".$from->locale('id')->translatedFormat('F Y').'.');
    }

    public function copyPrevious(Request $request)
    {
        abort_unless(! $request->user()->isSales(), 403);
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'overwrite' => ['nullable', 'boolean'],
        ]);
        $from = CarbonImmutable::createFromFormat('!Y-m', $data['period'])->startOfMonth();
        $previous = $from->subMonth()->startOfMonth();
        $copied = 0;

        DB::transaction(function () use ($request, $from, $previous, &$copied) {
            foreach ($this->visibleSales($request->user()) as $sales) {
                $source = SalesKpiTarget::where('user_id', $sales->id)
                    ->whereDate('period_start', $previous)->first();
                if (! $source) continue;
                $target = $this->monthlyTarget($sales->id, $from);
                if ($target->exists && ! $request->boolean('overwrite')) continue;
                $target->fill($source->only([
                    'sales_target', 'noo_target', 'custom_noo_target', 'large_account_target',
                    'drink_volume_target', 'food_volume_target',
                ]) + ['updated_by' => $request->user()->id])->save();
                $copied++;
            }
        });

        return back()->with('success', "Target bulan sebelumnya disalin ke {$copied} sales untuk ".$from->locale('id')->translatedFormat('F Y').'.');
    }

    private function validateTargetValues(Request $request): array
    {
        return $request->validate([
            'sales_target' => ['required', 'numeric', 'min:0'],
            'noo_target' => ['nullable', 'integer', 'min:0'],
            'custom_noo_target' => ['nullable', 'integer', 'min:0'],
            'large_account_target' => ['nullable', 'integer', 'min:0'],
            'drink_volume_target' => ['nullable', 'integer', 'min:0'],
            'food_volume_target' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function enabledMetricValues(array $metrics, int|float ...$values): array
    {
        $keys = ['noo', 'custom_noo', 'large_account', 'drink', 'food'];

        return collect($values)->chunk(2)->values()->filter(function ($pair, int $index) use ($keys, $metrics) {
            return $metrics[$keys[$index]]['enabled'] ?? false;
        })->flatten()->all();
    }

    private function operationalAchievement(array $values): float
    {
        $ratios = collect($values)->chunk(2)
            ->map(fn ($pair) => $pair->values())
            ->filter(fn ($pair) => (float) $pair[1] > 0)
            ->map(fn ($pair) => ((float) $pair[0] / (float) $pair[1]) * 100);

        return $ratios->isNotEmpty() ? round($ratios->avg(), 1) : 0;
    }

    private function monthlyTarget(int $userId, CarbonImmutable $from): SalesKpiTarget
    {
        return SalesKpiTarget::query()
            ->where('user_id', $userId)
            ->whereDate('period_start', $from->toDateString())
            ->whereDate('period_end', $from->endOfMonth()->toDateString())
            ->first() ?? new SalesKpiTarget([
                'user_id' => $userId,
                'period_start' => $from->toDateString(),
                'period_end' => $from->endOfMonth()->toDateString(),
            ]);
    }

    private function validateTemplateRole(string $roleSlug): string
    {
        abort_unless(in_array($roleSlug, ['sales', 'telesales'], true), 404);

        return $roleSlug;
    }

    private function period(Request $request): array
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->start_date)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->end_date)) {
            try {
                $from = CarbonImmutable::createFromFormat('Y-m-d', $request->start_date)->startOfDay();
                $to = CarbonImmutable::createFromFormat('Y-m-d', $request->end_date)->endOfDay();
            } catch (\Throwable) {
                $from = CarbonImmutable::now()->startOfMonth();
                $to = CarbonImmutable::now()->endOfMonth();
            }
        } else {
        $legacyPeriod = preg_match('/^\d{4}-\d{2}$/', (string) $request->period) ? $request->period : null;
        $startPeriod = preg_match('/^\d{4}-\d{2}$/', (string) $request->start_period) ? $request->start_period : $legacyPeriod;
        $endPeriod = preg_match('/^\d{4}-\d{2}$/', (string) $request->end_period) ? $request->end_period : $legacyPeriod;
        $from = CarbonImmutable::createFromFormat('!Y-m', $startPeriod ?: now()->format('Y-m'))->startOfMonth();
        $to = CarbonImmutable::createFromFormat('!Y-m', $endPeriod ?: $from->format('Y-m'))->endOfMonth();
        }

        if ($from->greaterThan($to)) [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        if ($from->diffInDays($to) > 1826) $from = $to->copy()->subYears(5)->startOfDay();

        return [$from, $to];
    }

    private function visibleSales(User $user)
    {
        $query = User::with(['manager', 'roles:id,name,slug'])->where('is_active', true)->whereHas('roles', fn ($q) => $q->whereIn('slug', ['sales', 'telesales']));
        if ($user->isSales()) return $query->whereKey($user->id)->get();
        if (!$user->isMasterAdmin()) {
            $ids = $user->subordinates()->pluck('id');
            $ids = $ids->merge(User::whereIn('manager_id', $ids)->pluck('id'))->push($user->id)->unique();
            $query->whereIn('id', $ids);
        }
        return $query->orderBy('name')->get();
    }

    private function canManage(User $actor, User $sales): bool
    {
        if (! $actor->canAccess('kpi.manage')) return false;
        if ($actor->isSales()) return false;
        if ($actor->isMasterAdmin()) return true;
        return $sales->manager_id === $actor->id || $sales->manager?->manager_id === $actor->id;
    }
}
