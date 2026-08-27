<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EvidenceThumbnailGenerator
{
    public function generate(Attachment $attachment): ?string
    {
        $disk = Storage::disk('public');
        $sourcePath = data_get($attachment->evidence_metadata, 'optimized_preview_path')
            ?: data_get($attachment->evidence_metadata, 'preview_path')
            ?: $attachment->path;

        if (! $sourcePath || ! $disk->exists($sourcePath)) {
            return null;
        }

        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $source = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($disk->path($sourcePath)),
            'png' => @imagecreatefrompng($disk->path($sourcePath)),
            'webp' => @imagecreatefromwebp($disk->path($sourcePath)),
            default => false,
        };

        if (! $source) {
            return null;
        }

        try {
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            if ($sourceWidth < 1 || $sourceHeight < 1) {
                return null;
            }

            // Keep list thumbnails tiny and fast. The fullscreen lightbox still
            // loads the separate HD WebP, so this file only needs to cover cards.
            $size = 192;
            $scale = max($size / $sourceWidth, $size / $sourceHeight);
            $resizeWidth = max($size, (int) ceil($sourceWidth * $scale));
            $resizeHeight = max($size, (int) ceil($sourceHeight * $scale));
            $offsetX = (int) floor(($size - $resizeWidth) / 2);
            $offsetY = (int) floor(($size - $resizeHeight) / 2);
            $thumbnail = imagecreatetruecolor($size, $size);
            imagefill($thumbnail, 0, 0, imagecolorallocate($thumbnail, 248, 250, 252));
            imagecopyresampled($thumbnail, $source, $offsetX, $offsetY, 0, 0, $resizeWidth, $resizeHeight, $sourceWidth, $sourceHeight);

            $thumbnailPath = 'crm/activity-thumbnails/attachment-'.$attachment->id.'.jpg';
            $disk->makeDirectory(dirname($thumbnailPath));
            imagejpeg($thumbnail, $disk->path($thumbnailPath), 78);
            imagedestroy($thumbnail);

            $attachment->update([
                'evidence_metadata' => array_merge($attachment->evidence_metadata ?? [], [
                    'thumbnail_path' => $thumbnailPath,
                ]),
            ]);

            return $thumbnailPath;
        } catch (Throwable) {
            return null;
        } finally {
            imagedestroy($source);
        }
    }
}
