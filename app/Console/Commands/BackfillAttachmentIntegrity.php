<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Services\ImageEvidenceInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillAttachmentIntegrity extends Command
{
    protected $signature = 'evidence:backfill-integrity {--force : Baca ulang metadata semua attachment}';
    protected $description = 'Generate integrity hash and readable metadata for existing evidence files';

    public function handle(ImageEvidenceInspector $inspector): int
    {
        $updated = 0;

        $query = Attachment::query();
        if (! $this->option('force')) $query->whereNull('sha256');

        $query->chunkById(100, function ($attachments) use ($inspector, &$updated) {
            foreach ($attachments as $attachment) {
                $path = Storage::disk('public')->path($attachment->path);
                if (! is_file($path)) continue;

                $attachment->update($inspector->inspectStoredFile($path, (string) $attachment->mime_type));
                $updated++;
            }
        });

        $duplicateHashes = Attachment::query()
            ->select('sha256')
            ->whereNotNull('sha256')
            ->groupBy('sha256')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('sha256');

        Attachment::query()->whereIn('sha256', $duplicateHashes)->each(function (Attachment $attachment) {
            $notes = collect($attachment->verification_notes ?? []);
            $message = 'File identik dengan bukti yang pernah diupload sebelumnya.';
            if (! $notes->contains($message)) $notes->push($message);
            $attachment->update(['verification_status' => 'duplicate', 'verification_notes' => $notes->values()->all()]);
        });

        $this->info("{$updated} attachment diperbarui; {$duplicateHashes->count()} hash duplikat ditemukan.");

        return self::SUCCESS;
    }
}
