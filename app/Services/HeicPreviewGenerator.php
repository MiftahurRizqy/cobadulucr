<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class HeicPreviewGenerator
{
    public function generate(string $storedPath, string $originalName): ?array
    {
        if (! in_array(strtolower(pathinfo($originalName, PATHINFO_EXTENSION)), ['heic', 'heif'], true)) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($storedPath)) return null;

        $previewPath = 'crm/activity-previews/'.pathinfo($storedPath, PATHINFO_FILENAME).'-'.Str::lower(Str::random(6)).'.jpg';
        $metadataPath = $previewPath.'.json';
        $process = new Process([
            'node',
            base_path('scripts/convert-heic.mjs'),
            $disk->path($storedPath),
            $disk->path($previewPath),
            $disk->path($metadataPath),
        ], base_path(), null, null, 45);
        $process->run();

        if (! $process->isSuccessful() || ! $disk->exists($previewPath)) return null;

        $parsed = $disk->exists($metadataPath)
            ? json_decode($disk->get($metadataPath), true) ?: []
            : [];
        $disk->delete($metadataPath);

        $capturedAt = filled($parsed['captured_at'] ?? null)
            ? CarbonImmutable::parse($parsed['captured_at'])->setTimezone(config('app.timezone'))
            : null;
        unset($parsed['captured_at']);

        return [
            'preview_path' => $previewPath,
            'captured_at' => $capturedAt,
            'metadata' => array_filter($parsed, fn ($value) => $value !== null),
        ];
    }
}
