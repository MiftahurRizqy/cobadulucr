<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class AiEvidenceDetector
{
    private const STRONG_MARKERS = [
        'midjourney', 'stable diffusion', 'stablediffusion', 'automatic1111',
        'comfyui', 'dall-e', 'dall·e', 'openai image', 'adobe firefly',
        'generative fill', 'invokeai', 'fooocus', 'novelai', 'leonardo.ai',
        'ideogram', 'generated with ai', 'ai-generated', 'flux.1',
        'chatgpt', 'com.openai', 'trainedalgorithmicmedia', 'trained algorithmic media',
    ];

    public function analyze(Attachment $attachment): array
    {
        $metadata = $attachment->evidence_metadata ?? [];
        $disk = Storage::disk('public');
        $absolutePath = $disk->exists($attachment->path) ? $disk->path($attachment->path) : null;
        $software = mb_strtolower((string) data_get($metadata, 'software', ''));
        $name = mb_strtolower($attachment->name);
        $searchable = $software.' '.$name.' '.$this->embeddedText($absolutePath);
        $markers = collect(self::STRONG_MARKERS)
            ->filter(fn (string $marker) => str_contains($searchable, $marker))
            ->values();

        $score = $markers->isNotEmpty() ? 85 : 0;
        $reasons = $markers->map(fn (string $marker) => 'Penanda generator ditemukan: '.$marker)->all();
        $hasCamera = filled(data_get($metadata, 'camera_make')) || filled(data_get($metadata, 'camera_model'));
        $isDirectCamera = data_get($metadata, 'capture_source') === 'camera';

        if (! $hasCamera && ! $isDirectCamera && str_starts_with((string) $attachment->mime_type, 'image/')) {
            $score += 15;
            $reasons[] = 'Tidak ada metadata kamera yang dapat diverifikasi.';
        }

        $dimensions = $absolutePath ? @getimagesize($absolutePath) : false;
        if ($dimensions) {
            [$width, $height] = $dimensions;
            $metadata['source_width'] = $width;
            $metadata['source_height'] = $height;
            if ($width === $height && in_array($width, [512, 768, 1024, 1536, 2048], true)) {
                $score += 10;
                $reasons[] = 'Dimensi persegi umum pada gambar sintetis.';
            }
        }

        $level = $score >= 70 ? 'suspected' : ($score >= 25 ? 'review' : 'no_indication');
        $metadata['ai_detection'] = [
            'level' => $level,
            'score' => min(100, $score),
            'reasons' => $reasons,
            'detector_version' => 1,
            'checked_at' => now()->toIso8601String(),
        ];

        $status = $attachment->verification_status;
        if ($level === 'suspected' && ! in_array($status, ['duplicate', 'tampered'], true)) {
            $status = 'ai_suspected';
        } elseif ($level === 'review' && ! in_array($status, ['warning', 'duplicate', 'tampered'], true)) {
            $status = 'ai_review';
        }

        $notes = collect($attachment->verification_notes ?? []);
        if ($level === 'suspected') {
            $notes->push('Gambar terindikasi dibuat atau diproses menggunakan generator AI. Perlu verifikasi manual.');
        } elseif ($level === 'review') {
            $notes->push('Keaslian sumber gambar belum dapat dipastikan. Perlu verifikasi manual.');
        }

        $attachment->update([
            'verification_status' => $status,
            'evidence_metadata' => $metadata,
            'verification_notes' => $notes->unique()->values()->all(),
        ]);

        return $metadata['ai_detection'];
    }

    private function embeddedText(?string $absolutePath): string
    {
        if (! $absolutePath || ! is_file($absolutePath)) {
            return '';
        }

        $handle = @fopen($absolutePath, 'rb');
        if (! $handle) {
            return '';
        }
        $chunk = fread($handle, 2 * 1024 * 1024) ?: '';
        fclose($handle);

        return mb_strtolower(preg_replace('/[^\x20-\x7E]+/', ' ', $chunk) ?? '');
    }
}
