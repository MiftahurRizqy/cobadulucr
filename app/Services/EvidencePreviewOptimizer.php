<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EvidencePreviewOptimizer
{
    public function optimize(Attachment $attachment): ?string
    {
        $disk = Storage::disk('public');
        $metadata = $attachment->evidence_metadata ?? [];
        $sourcePath = data_get($metadata, 'preview_path')
            ?: data_get($metadata, 'optimized_preview_path')
            ?: $attachment->path;

        if (! $sourcePath || ! $disk->exists($sourcePath)) {
            return null;
        }

        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if ($extension === 'pdf') {
            $attachment->update([
                'evidence_metadata' => array_merge($metadata, [
                    'optimization_status' => 'original_preserved',
                    'optimization_note' => 'PDF dimuat hanya saat dibuka.',
                ]),
            ]);

            return null;
        }

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
            $source = $this->applyExifOrientation($source, $disk->path($sourcePath), $extension);
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            if ($sourceWidth < 1 || $sourceHeight < 1) {
                return null;
            }

            // Keep enough detail for zoom. Compression is intentionally light;
            // thumbnails handle fast list rendering separately.
            // 2560px remains crisp on Full HD displays and moderate zoom while
            // substantially reducing storage compared with full camera resolution.
            $maxDimension = 2560;
            $scale = min(1, $maxDimension / max($sourceWidth, $sourceHeight));
            $targetWidth = max(1, (int) round($sourceWidth * $scale));
            $targetHeight = max(1, (int) round($sourceHeight * $scale));
            $preview = imagecreatetruecolor($targetWidth, $targetHeight);
            imagefill($preview, 0, 0, imagecolorallocate($preview, 255, 255, 255));
            imagecopyresampled($preview, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

            $optimizedPath = 'crm/activity-previews/attachment-'.$attachment->id.'-optimized.jpg';
            $disk->makeDirectory(dirname($optimizedPath));
            imagejpeg($preview, $disk->path($optimizedPath), 88);
            imagedestroy($preview);

            if (! $disk->exists($optimizedPath)) {
                return null;
            }

            $storedSize = $disk->size($attachment->path);
            $originalSize = (int) (data_get($metadata, 'original_size') ?: $storedSize);
            $optimizedSize = $disk->size($optimizedPath);
            $sourceSha256 = $attachment->sha256 ?: hash_file('sha256', $disk->path($attachment->path));
            $originalPath = $attachment->path;
            $intermediatePreviewPath = data_get($metadata, 'preview_path');
            $previousOptimizedPath = data_get($metadata, 'optimized_preview_path');

            $mustConvertForBrowser = in_array(
                strtolower(pathinfo((string) data_get($metadata, 'original_name', $attachment->name), PATHINFO_EXTENSION)),
                ['heic', 'heif', 'webp'],
                true
            ) || in_array($attachment->mime_type, ['image/heic', 'image/heif', 'image/webp'], true);

            if ($optimizedSize >= $storedSize && ! $mustConvertForBrowser) {
                $disk->delete($optimizedPath);
                foreach (array_unique(array_filter([$intermediatePreviewPath, $previousOptimizedPath])) as $obsoletePath) {
                    if ($obsoletePath !== $originalPath) {
                        $disk->delete($obsoletePath);
                    }
                }
                unset($metadata['preview_path'], $metadata['optimized_preview_path']);
                $attachment->update([
                    'evidence_metadata' => array_merge($metadata, [
                        'source_sha256' => $sourceSha256,
                        'original_name' => $attachment->name,
                        'original_mime_type' => $attachment->mime_type,
                        'original_size' => $originalSize,
                        'optimization_status' => 'already_optimized',
                        'optimization_saving_percent' => 0,
                    ]),
                ]);

                return $originalPath;
            }

            $finalPath = 'crm/activities/attachment-'.$attachment->id.'.jpg';
            $disk->delete($finalPath);
            $disk->move($optimizedPath, $finalPath);
            foreach (array_unique(array_filter([$originalPath, $intermediatePreviewPath, $previousOptimizedPath])) as $obsoletePath) {
                if ($obsoletePath !== $finalPath) {
                    $disk->delete($obsoletePath);
                }
            }
            $optimizedSize = $disk->size($finalPath);
            unset($metadata['preview_path']);
            $attachment->update([
                'evidence_metadata' => array_merge($metadata, [
                    'source_sha256' => $sourceSha256,
                    'original_name' => $attachment->name,
                    'original_mime_type' => $attachment->mime_type,
                    'original_size' => $originalSize,
                    'optimized_preview_path' => $finalPath,
                    'optimized_preview_size' => $optimizedSize,
                    'optimized_width' => $targetWidth,
                    'optimized_height' => $targetHeight,
                    'optimization_saving_percent' => $originalSize > 0
                        ? max(0, (int) round((1 - ($optimizedSize / $originalSize)) * 100))
                        : 0,
                    'optimization_status' => 'optimized',
                ]),
                'path' => $finalPath,
                'mime_type' => 'image/jpeg',
                'size' => $optimizedSize,
                'sha256' => hash_file('sha256', $disk->path($finalPath)),
            ]);

            return $finalPath;
        } catch (Throwable) {
            return null;
        } finally {
            imagedestroy($source);
        }
    }

    private function applyExifOrientation(\GdImage $source, string $absolutePath, string $extension): \GdImage
    {
        if (! in_array($extension, ['jpg', 'jpeg'], true) || ! function_exists('exif_read_data')) {
            return $source;
        }

        try {
            $orientation = (int) (exif_read_data($absolutePath, 'IFD0', true, false)['IFD0']['Orientation'] ?? 1);

            if ($orientation === 2) {
                imageflip($source, IMG_FLIP_HORIZONTAL);
            } elseif ($orientation === 3) {
                $source = $this->rotate($source, 180);
            } elseif ($orientation === 4) {
                imageflip($source, IMG_FLIP_VERTICAL);
            } elseif ($orientation === 5) {
                imageflip($source, IMG_FLIP_HORIZONTAL);
                $source = $this->rotate($source, 90);
            } elseif ($orientation === 6) {
                $source = $this->rotate($source, -90);
            } elseif ($orientation === 7) {
                imageflip($source, IMG_FLIP_HORIZONTAL);
                $source = $this->rotate($source, -90);
            } elseif ($orientation === 8) {
                $source = $this->rotate($source, 90);
            }
        } catch (Throwable) {
            return $source;
        }

        return $source;
    }

    private function rotate(\GdImage $source, int $degrees): \GdImage
    {
        $rotated = imagerotate($source, $degrees, imagecolorallocatealpha($source, 255, 255, 255, 127));
        if (! $rotated) {
            return $source;
        }

        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);
        imagedestroy($source);

        return $rotated;
    }
}
