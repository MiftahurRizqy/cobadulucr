<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

class ImageEvidenceInspector
{
    public function inspectUpload(UploadedFile $file, array $clientMetadata = []): array
    {
        return $this->inspect(
            $file->getRealPath(),
            (string) $file->getMimeType(),
            $clientMetadata,
        );
    }

    public function inspectStoredFile(string $absolutePath, string $mimeType): array
    {
        return $this->inspect($absolutePath, $mimeType, []);
    }

    private function inspect(string $absolutePath, string $mimeType, array $clientMetadata): array
    {
        $now = CarbonImmutable::now();
        $capturedAt = null;
        $clientModifiedAt = $this->clientModifiedAt($clientMetadata['lastModified'] ?? null);
        $deviceLocationRecordedAt = $this->clientModifiedAt($clientMetadata['locationRecordedAt'] ?? null);
        $deviceLatitude = $this->coordinate($clientMetadata['latitude'] ?? null, -90, 90);
        $deviceLongitude = $this->coordinate($clientMetadata['longitude'] ?? null, -180, 180);
        $deviceAccuracy = $this->accuracy($clientMetadata['accuracy'] ?? null);
        $hasDeviceLocation = $deviceLatitude !== null && $deviceLongitude !== null;
        $status = 'unavailable';
        $notes = [];
        $metadata = [
            'mime_type' => $mimeType,
            'camera_make' => null,
            'camera_model' => null,
            'software' => null,
            'gps_present' => false,
            'gps_latitude' => null,
            'gps_longitude' => null,
            'gps_altitude' => null,
            'exif_date_raw' => null,
            'capture_source' => in_array($clientMetadata['source'] ?? null, ['camera', 'upload'], true)
                ? $clientMetadata['source']
                : 'upload',
            'device_latitude' => $hasDeviceLocation ? $deviceLatitude : null,
            'device_longitude' => $hasDeviceLocation ? $deviceLongitude : null,
            'device_accuracy' => $hasDeviceLocation ? $deviceAccuracy : null,
            'device_location_recorded_at' => $deviceLocationRecordedAt?->toIso8601String(),
        ];

        if (str_starts_with($mimeType, 'image/') || in_array($mimeType, ['image/heic', 'image/heif', 'application/heic', 'application/heif'], true)) {
            if (in_array($mimeType, ['image/jpeg', 'image/jpg'], true) && function_exists('exif_read_data')) {
                $exif = @exif_read_data($absolutePath, null, true, false) ?: [];
                $rawDate = data_get($exif, 'EXIF.DateTimeOriginal')
                    ?? data_get($exif, 'EXIF.DateTimeDigitized')
                    ?? data_get($exif, 'IFD0.DateTime');

                $capturedAt = $this->exifDate($rawDate);
                $metadata['camera_make'] = $this->clean(data_get($exif, 'IFD0.Make'));
                $metadata['camera_model'] = $this->clean(data_get($exif, 'IFD0.Model'));
                $metadata['software'] = $this->clean(data_get($exif, 'IFD0.Software'));
                $metadata['gps_present'] = ! empty($exif['GPS']);
                $metadata['gps_latitude'] = $this->gpsCoordinate(
                    data_get($exif, 'GPS.GPSLatitude'),
                    data_get($exif, 'GPS.GPSLatitudeRef'),
                );
                $metadata['gps_longitude'] = $this->gpsCoordinate(
                    data_get($exif, 'GPS.GPSLongitude'),
                    data_get($exif, 'GPS.GPSLongitudeRef'),
                );
                $metadata['gps_altitude'] = $this->gpsAltitude(
                    data_get($exif, 'GPS.GPSAltitude'),
                    data_get($exif, 'GPS.GPSAltitudeRef'),
                );
                $metadata['exif_date_raw'] = $rawDate;

                if ($capturedAt) {
                    $status = 'metadata_found';
                } else {
                    $notes[] = 'Tanggal pengambilan tidak ditemukan pada EXIF.';
                }
            } else {
                $notes[] = 'Format gambar tidak menyediakan EXIF tanggal yang dapat dibaca.';
            }
        } else {
            $status = 'not_image';
            $notes[] = 'Lampiran bukan gambar.';
        }

        if ($capturedAt?->greaterThan($now->addMinutes(10))) {
            $status = 'warning';
            $notes[] = 'Tanggal pengambilan berada di masa depan.';
        }

        if ($clientModifiedAt?->greaterThan($now->addMinutes(10))) {
            $status = 'warning';
            $notes[] = 'Waktu file dari perangkat berada di masa depan.';
        }

        if ($deviceLocationRecordedAt?->greaterThan($now->addMinutes(10))) {
            $status = 'warning';
            $notes[] = 'Waktu pencatatan lokasi perangkat berada di masa depan.';
        }

        if ($capturedAt && $clientModifiedAt && abs($capturedAt->diffInSeconds($clientModifiedAt, false)) > 86400) {
            $notes[] = 'Tanggal EXIF dan waktu file perangkat berbeda lebih dari 24 jam.';
            if ($status !== 'warning') $status = 'review';
        }

        if (! $capturedAt && $clientModifiedAt && $status === 'unavailable') {
            $status = 'client_time_only';
        }

        if ($hasDeviceLocation && in_array($status, ['unavailable', 'client_time_only'], true)) {
            $status = 'device_location';
        }

        return [
            'sha256' => is_file($absolutePath) ? hash_file('sha256', $absolutePath) : null,
            'captured_at' => $capturedAt,
            'client_modified_at' => $clientModifiedAt,
            'verification_status' => $status,
            'evidence_metadata' => $metadata,
            'verification_notes' => $notes,
        ];
    }

    private function exifDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || blank($value)) return null;

        try {
            return CarbonImmutable::createFromFormat('Y:m:d H:i:s', trim($value), config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function clientModifiedAt(mixed $milliseconds): ?CarbonImmutable
    {
        if (! is_numeric($milliseconds) || (int) $milliseconds <= 0) return null;

        try {
            return CarbonImmutable::createFromTimestampUTC((int) floor(((int) $milliseconds) / 1000))
                ->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function clean(mixed $value): ?string
    {
        return is_string($value) && filled(trim($value)) ? mb_substr(trim($value), 0, 255) : null;
    }

    private function coordinate(mixed $value, float $minimum, float $maximum): ?float
    {
        if (! is_numeric($value)) return null;

        $coordinate = (float) $value;
        if (! is_finite($coordinate) || $coordinate < $minimum || $coordinate > $maximum) return null;

        return round($coordinate, 7);
    }

    private function accuracy(mixed $value): ?float
    {
        if (! is_numeric($value)) return null;

        $accuracy = (float) $value;
        if (! is_finite($accuracy) || $accuracy < 0 || $accuracy > 100000) return null;

        return round($accuracy, 2);
    }

    private function gpsCoordinate(mixed $parts, mixed $reference): ?float
    {
        if (! is_array($parts) || count($parts) < 3) return null;

        $degrees = $this->rational($parts[0]);
        $minutes = $this->rational($parts[1]);
        $seconds = $this->rational($parts[2]);
        if ($degrees === null || $minutes === null || $seconds === null) return null;

        $coordinate = $degrees + ($minutes / 60) + ($seconds / 3600);
        if (in_array(strtoupper((string) $reference), ['S', 'W'], true)) $coordinate *= -1;

        return round($coordinate, 7);
    }

    private function gpsAltitude(mixed $altitude, mixed $reference): ?float
    {
        $value = $this->rational($altitude);
        if ($value === null) return null;

        return ((int) $reference === 1 ? -1 : 1) * round($value, 2);
    }

    private function rational(mixed $value): ?float
    {
        if (is_numeric($value)) return (float) $value;
        if (! is_string($value) || ! str_contains($value, '/')) return null;

        [$numerator, $denominator] = array_pad(explode('/', $value, 2), 2, null);
        if (! is_numeric($numerator) || ! is_numeric($denominator) || (float) $denominator === 0.0) return null;

        return (float) $numerator / (float) $denominator;
    }
}
