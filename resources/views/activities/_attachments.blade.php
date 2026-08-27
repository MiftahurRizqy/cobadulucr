@if($activity->attachments->isNotEmpty())
@php
    $evidenceItems = $activity->attachments;
    $evidenceAutoExpanded = $evidenceInitiallyOpen ?? false;
    $deferEvidenceImages = $deferEvidenceImages ?? false;
    $inlineEvidenceThumbnails = $inlineEvidenceThumbnails ?? false;
    $imageCount = $evidenceItems->filter(fn ($item) => str_starts_with($item->mime_type ?? '', 'image/')
        || in_array(strtolower(pathinfo($item->name, PATHINFO_EXTENSION)), ['heic', 'heif'], true))->count();
    $locationCount = $evidenceItems->filter(fn ($item) =>
        data_get($item->evidence_metadata, 'gps_latitude') !== null
        || data_get($item->evidence_metadata, 'device_latitude') !== null
    )->count();
    $canReviewEvidenceIntegrity = $canReviewEvidenceIntegrity ?? ! auth()->user()->isSales();
    $warningCount = $canReviewEvidenceIntegrity
        ? $evidenceItems->whereIn('verification_status', ['warning', 'review', 'duplicate', 'tampered', 'ai_suspected', 'ai_review'])->count()
        : 0;
@endphp

<div class="{{ $evidenceAutoExpanded ? 'mt-0' : 'mt-3' }} text-left" x-data="{ evidenceOpen: {{ $evidenceAutoExpanded ? 'true' : 'false' }} }">
    @unless($evidenceAutoExpanded)
    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-2.5">
        <div class="flex -space-x-2">
            @foreach($evidenceItems->take(4) as $attachment)
                @php
                    $isHeic = in_array(strtolower(pathinfo($attachment->name, PATHINFO_EXTENSION)), ['heic', 'heif'], true);
                    $previewPath = data_get($attachment->evidence_metadata, 'preview_path');
                    $previewUrl = $previewPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($previewPath) : null;
                    $optimizedPath = data_get($attachment->evidence_metadata, 'optimized_preview_path');
                    $optimizedUrl = $optimizedPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($optimizedPath) : null;
                    $thumbnailPath = data_get($attachment->evidence_metadata, 'thumbnail_path');
                    $thumbnailUrl = $thumbnailPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($thumbnailPath) : null;
                    $isImage = str_starts_with($attachment->mime_type ?? '', 'image/') || $isHeic;
                @endphp
                <a href="{{ $optimizedUrl ?: ($previewUrl ?: \Illuminate\Support\Facades\Storage::disk('public')->url($attachment->path)) }}" @if($isHeic && ! $optimizedUrl && ! $previewUrl) data-heic-link="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($attachment->path) }}" @endif @if($isImage) data-evidence-lightbox data-evidence-name="{{ $attachment->name }}" @else target="_blank" rel="noopener" @endif class="relative grid size-9 shrink-0 place-items-center overflow-hidden rounded-lg border-2 border-white bg-white shadow-sm" title="Buka {{ $attachment->name }}">
                    @if($thumbnailUrl)
                        <img src="{{ $thumbnailUrl }}" alt="{{ $attachment->name }}" class="h-full w-full object-cover" loading="lazy" decoding="async">
                    @elseif($previewUrl)
                        <img src="{{ $previewUrl }}" alt="{{ $attachment->name }}" class="h-full w-full object-cover" loading="lazy" decoding="async">
                    @elseif(str_starts_with($attachment->mime_type ?? '', 'image/') && ! $isHeic)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($attachment->path) }}" alt="{{ $attachment->name }}" class="h-full w-full object-cover" loading="lazy">
                    @elseif($isHeic)
                        <span data-heic-preview="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($attachment->path) }}" data-heic-alt="{{ $attachment->name }}" class="grid h-full w-full place-items-center text-[7px] font-black text-sky-600">HEIC</span>
                    @else
                        <span class="text-[8px] font-black text-rose-600">PDF</span>
                    @endif
                </a>
            @endforeach
            @if($evidenceItems->count() > 4)<span class="relative grid size-9 place-items-center rounded-lg border-2 border-white bg-slate-200 text-[9px] font-black text-slate-600">+{{ $evidenceItems->count() - 4 }}</span>@endif
        </div>

        <div class="min-w-0 flex-1">
            <div class="text-[10px] font-extrabold text-slate-700">{{ $evidenceItems->count() }} bukti aktivitas</div>
            <div class="mt-0.5 flex flex-wrap gap-x-2 text-[9px] text-slate-400">
                <span>{{ $imageCount }} gambar</span>
                @if($locationCount)<span class="font-semibold text-cyan-600">{{ $locationCount }} dengan lokasi</span>@endif
                @if($warningCount)<span class="font-semibold text-amber-600">{{ $warningCount }} perlu perhatian</span>@endif
            </div>
        </div>

        <button type="button" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-[9px] font-bold text-slate-600 shadow-sm transition hover:border-brand-200 hover:text-brand-600" @click="evidenceOpen = !evidenceOpen" :aria-expanded="evidenceOpen">
            <span x-text="evidenceOpen ? 'Tutup detail' : 'Detail bukti'"></span>
            <svg class="size-3 transition" :class="evidenceOpen && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
        </button>
    </div>
    @endunless

    <div @unless($evidenceAutoExpanded) x-show="evidenceOpen" x-cloak x-transition.opacity.duration.150ms @endunless class="{{ $evidenceAutoExpanded ? '' : 'mt-2' }} grid gap-2 {{ $evidenceItems->count() > 1 ? '2xl:grid-cols-2' : 'grid-cols-1' }}">
        @foreach($evidenceItems as $attachment)
            @php
                $isHeic = in_array(strtolower(pathinfo($attachment->name, PATHINFO_EXTENSION)), ['heic', 'heif'], true);
                $previewPath = data_get($attachment->evidence_metadata, 'preview_path');
                $previewUrl = $previewPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($previewPath) : null;
                $optimizedPath = data_get($attachment->evidence_metadata, 'optimized_preview_path');
                $optimizedUrl = $optimizedPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($optimizedPath) : null;
                $thumbnailPath = data_get($attachment->evidence_metadata, 'thumbnail_path');
                $thumbnailUrl = $thumbnailPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($thumbnailPath) : null;
                $thumbnailData = null;
                if ($inlineEvidenceThumbnails && $thumbnailPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($thumbnailPath)) {
                    $thumbnailData = 'data:image/jpeg;base64,'.base64_encode(\Illuminate\Support\Facades\Storage::disk('public')->get($thumbnailPath));
                }
                $isImage = str_starts_with($attachment->mime_type ?? '', 'image/') || $isHeic;
                $status = $attachment->verification_status;
                $statusStyle = match($status) {
                    'metadata_found' => 'bg-emerald-50 text-emerald-600',
                    'device_location' => 'bg-cyan-50 text-cyan-700',
                    'client_time_only' => 'bg-sky-50 text-sky-600',
                    'review', 'warning' => 'bg-amber-50 text-amber-700',
                    'duplicate' => 'bg-orange-50 text-orange-700',
                    'ai_suspected' => 'bg-fuchsia-100 text-fuchsia-800',
                    'ai_review' => 'bg-violet-50 text-violet-700',
                    'tampered' => 'bg-rose-50 text-rose-700',
                    default => 'bg-slate-100 text-slate-500',
                };
                $statusLabel = match($status) {
                    'metadata_found' => 'Metadata kamera ditemukan',
                    'device_location' => 'Lokasi perangkat tercatat',
                    'client_time_only' => 'Hanya waktu perangkat',
                    'review' => 'Perlu diperiksa',
                    'warning' => 'Metadata mencurigakan',
                    'duplicate' => 'Duplikat bukti sebelumnya',
                    'ai_suspected' => 'Terindikasi AI',
                    'ai_review' => 'Keaslian perlu diperiksa',
                    'tampered' => 'File telah berubah',
                    'not_image' => 'Dokumen pendukung',
                    default => 'EXIF tidak tersedia',
                };
                $camera = collect([data_get($attachment->evidence_metadata, 'camera_make'), data_get($attachment->evidence_metadata, 'camera_model')])->filter()->join(' ');
                $exifLatitude = data_get($attachment->evidence_metadata, 'gps_latitude');
                $exifLongitude = data_get($attachment->evidence_metadata, 'gps_longitude');
                $deviceLatitude = data_get($attachment->evidence_metadata, 'device_latitude');
                $deviceLongitude = data_get($attachment->evidence_metadata, 'device_longitude');
                $latitude = $exifLatitude ?? $deviceLatitude;
                $longitude = $exifLongitude ?? $deviceLongitude;
                $locationSource = $exifLatitude !== null && $exifLongitude !== null ? 'exif' : (($deviceLatitude !== null && $deviceLongitude !== null) ? 'device' : null);
                $mapUrl = $latitude !== null && $longitude !== null ? 'https://www.google.com/maps?q='.$latitude.','.$longitude : null;
                $showClientModifiedAt = $attachment->client_modified_at
                    && (! $attachment->captured_at || abs($attachment->captured_at->diffInSeconds($attachment->client_modified_at, false)) > 60);
                $aiDetection = data_get($attachment->evidence_metadata, 'ai_detection');
            @endphp
            <article class="overflow-hidden rounded-xl border {{ $canReviewEvidenceIntegrity && $status === 'tampered' ? 'border-rose-300' : 'border-slate-200' }} bg-white text-left">
                <a href="{{ $optimizedUrl ?: ($previewUrl ?: \Illuminate\Support\Facades\Storage::disk('public')->url($attachment->path)) }}" @if($isHeic && ! $optimizedUrl && ! $previewUrl) data-heic-link="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($attachment->path) }}" @endif @if($isImage) data-evidence-lightbox data-evidence-name="{{ $attachment->name }}" @else target="_blank" rel="noopener" @endif class="group flex items-center gap-3 border-b border-slate-100 p-2.5 hover:bg-slate-50">
                    @if($thumbnailData)
                        <img src="{{ $thumbnailData }}" alt="{{ $attachment->name }}" class="size-11 shrink-0 rounded-lg object-cover" decoding="async">
                    @elseif($thumbnailUrl)
                        <img @if($deferEvidenceImages) data-evidence-src="{{ $thumbnailUrl }}" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" @else src="{{ $thumbnailUrl }}" @endif alt="{{ $attachment->name }}" class="size-11 shrink-0 rounded-lg object-cover" loading="lazy" decoding="async">
                    @elseif($previewUrl)
                        <img @if($deferEvidenceImages) data-evidence-src="{{ $previewUrl }}" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" @else src="{{ $previewUrl }}" @endif alt="{{ $attachment->name }}" class="size-11 shrink-0 rounded-lg object-cover" loading="lazy" decoding="async">
                    @elseif(str_starts_with($attachment->mime_type ?? '', 'image/') && ! $isHeic)
                        <img @if($deferEvidenceImages) data-evidence-src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($attachment->path) }}" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" @else src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($attachment->path) }}" @endif alt="{{ $attachment->name }}" class="size-11 shrink-0 rounded-lg object-cover" loading="lazy">
                    @elseif($isHeic)
                        <span data-heic-preview="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($attachment->path) }}" data-heic-alt="{{ $attachment->name }}" data-heic-class="size-11 shrink-0 rounded-lg object-cover" class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-lg bg-sky-50 text-[8px] font-black text-sky-600">HEIC</span>
                    @else
                        <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-rose-50 text-[8px] font-black text-rose-600">PDF</span>
                    @endif
                    <span class="min-w-0 flex-1"><span class="block truncate text-[11px] font-bold text-ink group-hover:text-brand-600">{{ $attachment->name }}</span><span class="mt-0.5 block text-[10px] text-slate-400">{{ number_format($attachment->size / 1024, 0, ',', '.') }} KB · Klik untuk membuka</span></span>
                    @if($canReviewEvidenceIntegrity)<span class="badge shrink-0 {{ $statusStyle }}">{{ $statusLabel }}</span>@endif
                </a>

                <div class="grid gap-3 p-3.5 text-[10px] sm:grid-cols-2">
                    <div><span class="block font-bold uppercase tracking-wider text-slate-400">Diupload</span><span class="mt-1 block font-semibold text-slate-600">{{ $attachment->created_at?->translatedFormat('d M Y, H:i:s') }}</span></div>
                    <div><span class="block font-bold uppercase tracking-wider text-slate-400">Diambil (EXIF)</span><span class="mt-1 block font-semibold {{ $attachment->captured_at ? 'text-slate-600' : 'text-amber-600' }}">{{ $attachment->captured_at?->translatedFormat('d M Y, H:i:s') ?? 'Tidak tersedia' }}</span></div>
                    @if($showClientModifiedAt)<div><span class="block font-bold uppercase tracking-wider text-slate-400">Waktu file perangkat</span><span class="mt-1 block font-semibold text-slate-600">{{ $attachment->client_modified_at->translatedFormat('d M Y, H:i:s') }}</span></div>@endif
                    <div><span class="block font-bold uppercase tracking-wider text-slate-400">Perangkat</span><span class="mt-1 block font-semibold text-slate-600">{{ $camera ?: 'Tidak terdeteksi' }}</span></div>
                    @if($canReviewEvidenceIntegrity && in_array(data_get($aiDetection, 'level'), ['suspected', 'review'], true))
                        <div class="sm:col-span-2 rounded-lg {{ data_get($aiDetection, 'level') === 'suspected' ? 'bg-fuchsia-50 text-fuchsia-800' : 'bg-violet-50 text-violet-700' }} p-2.5">
                            <div class="font-extrabold">{{ data_get($aiDetection, 'level') === 'suspected' ? 'Terindikasi gambar AI' : 'Keaslian gambar perlu diperiksa' }}</div>
                            <div class="mt-1 leading-relaxed">{{ collect(data_get($aiDetection, 'reasons', []))->join(' ') }}</div>
                            <div class="mt-1 text-[9px] opacity-70">Indikasi sistem, bukan keputusan mutlak. Tetap lakukan verifikasi manual.</div>
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <span class="block font-bold uppercase tracking-wider text-slate-400">{{ $locationSource === 'exif' ? 'Lokasi pengambilan (EXIF GPS)' : 'Lokasi perangkat saat kamera dibuka' }}</span>
                        @if($mapUrl)
                            <span class="mt-1 flex flex-wrap items-center gap-2"><span class="font-mono text-[9px] font-semibold text-slate-600">{{ number_format($latitude, 7, '.', '') }}, {{ number_format($longitude, 7, '.', '') }}</span><a href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer" class="font-bold text-brand-600 hover:text-brand-700">Buka di Google Maps →</a></span>
                        @else<span class="mt-1 block font-semibold text-amber-600">Lokasi tidak tersedia</span>@endif
                    </div>
                    @if($canReviewEvidenceIntegrity && $attachment->verification_notes)<div class="sm:col-span-2 rounded-lg bg-amber-50 p-2 text-amber-700">{{ collect($attachment->verification_notes)->join(' ') }}</div>@endif
                </div>
            </article>
        @endforeach
    </div>
</div>
@endif
