<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class EvidenceStorageCleaner
{
    public function clean(): array
    {
        $disk = Storage::disk('public');
        $referenced = Attachment::query()
            ->get(['path', 'evidence_metadata'])
            ->flatMap(fn (Attachment $attachment) => [
                $attachment->path,
                data_get($attachment->evidence_metadata, 'preview_path'),
                data_get($attachment->evidence_metadata, 'optimized_preview_path'),
                data_get($attachment->evidence_metadata, 'thumbnail_path'),
            ])
            ->filter()
            ->unique()
            ->flip();

        $deletedFiles = 0;
        $deletedBytes = 0;
        foreach (['crm/activities', 'crm/activity-previews', 'crm/activity-thumbnails'] as $directory) {
            foreach ($disk->allFiles($directory) as $path) {
                if ($referenced->has($path)) {
                    continue;
                }
                $deletedBytes += $disk->size($path);
                $disk->delete($path);
                $deletedFiles++;
            }
        }

        return compact('deletedFiles', 'deletedBytes');
    }
}
