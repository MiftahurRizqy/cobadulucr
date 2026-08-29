<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\Customer;
use App\Models\SalesKpiTarget;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class KpiController extends Controller
{
    public function index(Request $request)
    {
        $data = $this->reportData($request);

        return view('kpi.index', $data);
    }

    public function exportExcel(Request $request)
    {
        $data = $this->reportData($request);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('KPI '.$data['from']->format('Y-m'));
        $book->getDefaultStyle()->getFont()->setName('Aptos')->setSize(10)->getColor()->setRGB('0F172A');
        $sheet->setShowGridlines(false);
        $sheet->getSheetView()->setZoomScale(85);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(.45)->setRight(.35)->setBottom(.45)->setLeft(.35);
        $sheet->getHeaderFooter()->setOddFooter('&LLaporan KPI Penjualan&C&P / &N&RDicetak &D &T');
        $sheet->freezePane('A11');
        $sheet->mergeCells('A1:I1')->setCellValue('A1', 'UNIFIED CRM');
        $sheet->mergeCells('A2:I2')->setCellValue('A2', 'Laporan KPI Penjualan');
        $sheet->mergeCells('A3:I3')->setCellValue('A3', 'Periode '.$data['periodLabel'].'  |  Diterbitkan '.now()->translatedFormat('d F Y, H:i').' WIB');
        $sheet->getStyle('A1:I3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('4F46E5');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(20)->getColor()->setRGB('0F172A');
        $sheet->getStyle('A3')->getFont()->setSize(9)->getColor()->setRGB('64748B');
        $sheet->getStyle('A3:I3')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('312E81');
        $sheet->getRowDimension(1)->setRowHeight(18);
        $sheet->getRowDimension(2)->setRowHeight(28);
        $sheet->getRowDimension(3)->setRowHeight(24);
        $sheet->getRowDimension(4)->setRowHeight(8);

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
                $sheet->fromArray([$headRow->head->name, 'HEAD · '.$headRow->salesCount.' sales', $headRow->target, $headRow->realization, $headRow->deals, $headRow->closingRate / 100, $headRow->achievement / 100, '—', '-'], null, 'A'.$rowNumber);
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
                .'  ·  Custom '.number_format($row->customNoo).'/'.number_format((int) ($row->target?->custom_noo_target ?? 0))."\n"
                .'Drink '.number_format($row->drinkVolume).'/'.number_format((int) ($row->target?->drink_volume_target ?? 0))
                .'  ·  Food '.number_format($row->foodVolume).'/'.number_format((int) ($row->target?->food_volume_target ?? 0)).' pcs';
            $comment = $hasComment
                ? $row->target->evaluation_notes."\nBy ".($row->target?->updater?->name ?? '-').($commentTime ? ' · '.$commentTime : '')
                : '-';
            $sheet->fromArray([$row->sales->name, 'Sales', (float) ($row->target?->sales_target ?? 0), (float) $row->realization, $row->deals, $row->closingRate / 100, $row->achievement / 100, $operational, $comment], null, 'A'.$rowNumber);
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
        foreach (['A'=>25,'B'=>16,'C'=>17,'D'=>19,'E'=>8,'F'=>11,'G'=>13,'H'=>38,'I'=>38] as $column => $width) $sheet->getColumnDimension($column)->setWidth($width);
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
        return Pdf::loadView('kpi.export-pdf', $data)->setPaper('a4', 'landscape')
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
        $rows = $users->map(function (User $sales) use ($from, $to, $targets, $request) {
            $wonOpportunities = Opportunity::query()
                ->with('items')
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
                foreach (['sales_target', 'noo_target', 'custom_noo_target', 'drink_volume_target', 'food_volume_target'] as $field) {
                    $target->{$field} = $salesTargets->sum(fn ($item) => (float) $item->{$field});
                }
            }
            $realization = $wonOpportunities->sum(fn (Opportunity $opportunity) => $opportunity->realizedValue());
            $newCustomers = Customer::query()->where('sales_owner_id', $sales->id)->whereBetween('became_customer_at', [$from, $to]);
            $noo = (clone $newCustomers)->count();
            $customNoo = (clone $newCustomers)->whereHas('opportunities.pipeline', fn ($query) => $query->where('counts_as_custom_noo', true))->count();
            $wonItems = $wonOpportunities->flatMap->items;
            $drinkVolume = $wonItems->where('market_segment', 'drink')->sum('quantity');
            $foodVolume = $wonItems->where('market_segment', 'food')->sum('quantity');

            return (object) (compact('sales', 'target', 'realization', 'deals', 'opportunityCount', 'noo', 'customNoo', 'drinkVolume', 'foodVolume') + [
                'achievement' => $target && (float) $target->sales_target > 0 ? round($realization / (float) $target->sales_target * 100, 1) : 0,
                'closingRate' => $opportunityCount > 0 ? round($deals / $opportunityCount * 100, 1) : 0,
                'canManage' => $this->canManage($request->user(), $sales),
            ]);
        });

        $summary = (object) [
            'target' => $rows->sum(fn ($row) => (float) ($row->target?->sales_target ?? 0)),
            'realization' => $rows->sum('realization'),
            'deals' => $rows->sum('deals'),
            'achievement' => 0,
            'noo' => $rows->sum('noo'), 'customNoo' => $rows->sum('customNoo'),
            'drinkVolume' => $rows->sum('drinkVolume'), 'foodVolume' => $rows->sum('foodVolume'),
            'nooTarget' => $rows->sum(fn ($row) => (int) ($row->target?->noo_target ?? 0)),
            'customNooTarget' => $rows->sum(fn ($row) => (int) ($row->target?->custom_noo_target ?? 0)),
            'drinkVolumeTarget' => $rows->sum(fn ($row) => (int) ($row->target?->drink_volume_target ?? 0)),
            'foodVolumeTarget' => $rows->sum(fn ($row) => (int) ($row->target?->food_volume_target ?? 0)),
        ];
        $summary->achievement = $summary->target > 0 ? round($summary->realization / $summary->target * 100, 1) : 0;

        $headRows = $request->user()->isSales()
            ? collect()
            : $rows->filter(fn ($row) => $row->sales->manager)->groupBy(fn ($row) => $row->sales->manager_id)
                ->map(function ($salesRows) {
                    $target = $salesRows->sum(fn ($row) => (float) ($row->target?->sales_target ?? 0));
                    $realization = $salesRows->sum('realization');
                    $deals = $salesRows->sum('deals');
                    $opportunityCount = $salesRows->sum('opportunityCount');

                    return (object) [
                        'head' => $salesRows->first()->sales->manager,
                        'salesCount' => $salesRows->count(),
                        'target' => $target,
                        'realization' => $realization,
                        'deals' => $deals,
                        'closingRate' => $opportunityCount > 0 ? round($deals / $opportunityCount * 100, 1) : 0,
                        'achievement' => $target > 0 ? round($realization / $target * 100, 1) : 0,
                        'noo' => $salesRows->sum('noo'), 'customNoo' => $salesRows->sum('customNoo'),
                        'drinkVolume' => $salesRows->sum('drinkVolume'), 'foodVolume' => $salesRows->sum('foodVolume'),
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

        return compact('rows', 'headRows', 'displayRows', 'from', 'to', 'summary', 'isSingleMonth', 'periodLabel', 'exportKey');
    }

    public function update(Request $request, User $sales)
    {
        abort_unless($this->canManage($request->user(), $sales), 403);
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'], 'sales_target' => ['required', 'numeric', 'min:0'],
            'noo_target' => ['nullable', 'integer', 'min:0'], 'custom_noo_target' => ['nullable', 'integer', 'min:0'],
            'drink_volume_target' => ['nullable', 'integer', 'min:0'],
            'food_volume_target' => ['nullable', 'integer', 'min:0'],
            'evaluation_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $from = Carbon::createFromFormat('Y-m', $data['period'])->startOfMonth();
        SalesKpiTarget::updateOrCreate(
            ['user_id' => $sales->id, 'period_start' => $from->toDateString(), 'period_end' => $from->copy()->endOfMonth()->toDateString()],
            ['sales_target' => $data['sales_target'], 'noo_target' => $data['noo_target'] ?? 0, 'custom_noo_target' => $data['custom_noo_target'] ?? 0,
                'drink_volume_target' => $data['drink_volume_target'] ?? 0,
                'food_volume_target' => $data['food_volume_target'] ?? 0, 'evaluation_notes' => $data['evaluation_notes'] ?? null, 'updated_by' => $request->user()->id]
        );
        return back()->with('success', 'Target dan catatan KPI berhasil disimpan.');
    }

    private function period(Request $request): array
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->start_date)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->end_date)) {
            try {
                $from = Carbon::createFromFormat('Y-m-d', $request->start_date)->startOfDay();
                $to = Carbon::createFromFormat('Y-m-d', $request->end_date)->endOfDay();
            } catch (\Throwable) {
                $from = now()->startOfMonth();
                $to = now()->endOfMonth();
            }
        } else {
        $legacyPeriod = preg_match('/^\d{4}-\d{2}$/', (string) $request->period) ? $request->period : null;
        $startPeriod = preg_match('/^\d{4}-\d{2}$/', (string) $request->start_period) ? $request->start_period : $legacyPeriod;
        $endPeriod = preg_match('/^\d{4}-\d{2}$/', (string) $request->end_period) ? $request->end_period : $legacyPeriod;
        $from = Carbon::createFromFormat('Y-m', $startPeriod ?: now()->format('Y-m'))->startOfMonth();
        $to = Carbon::createFromFormat('Y-m', $endPeriod ?: $from->format('Y-m'))->endOfMonth();
        }

        if ($from->greaterThan($to)) [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        if ($from->diffInDays($to) > 1826) $from = $to->copy()->subYears(5)->startOfDay();

        return [$from, $to];
    }

    private function visibleSales(User $user)
    {
        $query = User::with('manager')->where('is_active', true)->whereHas('roles', fn ($q) => $q->where('slug', 'sales'));
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
        if ($actor->isSales()) return false;
        if ($actor->isMasterAdmin()) return true;
        return $sales->manager_id === $actor->id || $sales->manager?->manager_id === $actor->id;
    }
}
