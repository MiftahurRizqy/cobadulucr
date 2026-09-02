<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Laporan Leads</title>
<style>body{font-family:Arial,sans-serif;color:#172033;font-size:12px;margin:28px}h1{font-size:22px;margin-bottom:8px}table{width:100%;border-collapse:collapse;margin-top:18px}th,td{border:1px solid #d9e0ea;padding:8px;text-align:left}th{background:#f3f5f8}.summary td{width:25%;padding:16px}.summary strong{display:block;font-size:23px;margin-top:10px}.muted{color:#667085;font-size:11px;line-height:1.6}thead{display:table-header-group}tr{break-inside:avoid}@page{size:A4 landscape;margin:12mm}@media print{body{margin:0}.summary{break-inside:avoid}}</style></head>
<body>
<style>
body{max-width:1440px;margin:36px auto;padding:0 24px;font-size:11px;color:#252b36}
.report-header{display:flex;align-items:center;justify-content:space-between;gap:24px;padding-bottom:24px;border-bottom:2px solid #252b36}
.identity{display:flex;align-items:center;gap:18px}.identity img{width:66px;height:66px;object-fit:contain}.company{font-size:19px;font-weight:700;line-height:1.4}.descriptor{font-size:10px;color:#77808e;letter-spacing:1.4px;text-transform:uppercase;margin-top:6px}
.document-type{text-align:right;font-size:10px;letter-spacing:1px;color:#77808e}.report-title{display:flex;justify-content:space-between;align-items:flex-end;gap:24px;margin:28px 0 22px}h1{font-size:25px;letter-spacing:-.5px;margin:0 0 8px}.summary td{border-color:#e3e6eb;padding:18px;font-size:10px;color:#626b79}.summary strong{color:#252b36;font-size:25px}.summary{margin-bottom:18px}th{font-size:10px;background:#f3f4f6;color:#495262}td{font-size:10px;line-height:1.5;overflow-wrap:anywhere}tbody tr:nth-child(even){background:#fafbfc}.report-footer{display:flex;justify-content:space-between;gap:16px;margin-top:28px;border-top:1px solid #d9e0ea;padding-top:12px;color:#77808e;font-size:10px}
@media print{body{max-width:none;margin:0;padding:0}.report-header,.report-title{break-inside:avoid}.report-footer{break-inside:avoid}.report-title{margin-top:20px}}
@media(max-width:640px){.report-header,.report-title{align-items:flex-start;flex-direction:column}.document-type{text-align:left}.company{font-size:16px}body{padding:0 12px}}
</style>
<header class="report-header"><div class="identity">
@if($reportCompany?->logo_path)<img src="{{ asset('storage/'.$reportCompany->logo_path) }}" alt="Logo {{ $reportCompany->name }}">@endif
<div><div class="company">{{ $reportCompany?->name ?? 'CRM' }}</div><div class="descriptor">Customer Relationship Management</div></div></div><div class="document-type">SALES REPORT<br><span style="display:block;margin-top:6px;letter-spacing:0">Internal use only</span></div></header>
<div class="report-title"><div><h1>Laporan Leads</h1><div class="muted">Periode {{ $from ? \Carbon\Carbon::parse($from)->translatedFormat('d M Y') : 'Semua waktu' }} — {{ $to ? \Carbon\Carbon::parse($to)->translatedFormat('d M Y') : 'sekarang' }}</div></div><div class="muted">Diterbitkan<br><strong style="color:#495262;font-weight:500">{{ $exportedAt }}</strong></div></div>
<table class="summary"><tr>@foreach($summary as $label => $value)<td>{{ $label }}<strong>{{ $value }}</strong></td>@endforeach</tr></table>
<h2 style="font-size:14px;margin-top:26px">Detail leads · {{ $rows->count() }} data</h2>
<table><thead><tr>@foreach($columns as $column)<th>{{ $column['label'] }}</th>@endforeach</tr></thead><tbody>@forelse($rows as $lead)<tr>@foreach($columns as $column)<td>{{ $column['value']($lead) }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($columns) }}">Tidak ada lead sesuai filter.</td></tr>@endforelse</tbody></table>
<footer class="report-footer"><span>{{ $reportCompany?->name ?? 'CRM' }}</span><span>Dokumen internal · Laporan Leads</span></footer></body></html>
