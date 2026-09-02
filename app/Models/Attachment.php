<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use Auditable;

    protected $fillable = ['attachable_type', 'attachable_id', 'uploaded_by', 'name', 'path', 'mime_type', 'size', 'sha256', 'captured_at', 'client_modified_at', 'verification_status', 'evidence_metadata', 'verification_notes'];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'client_modified_at' => 'datetime',
            'evidence_metadata' => 'array',
            'verification_notes' => 'array',
        ];
    }

    public function checksumIsValid(): ?bool
    {
        if (! $this->sha256) {
            return null;
        }

        if (! Storage::disk('public')->exists($this->path)) {
            return false;
        }

        $stream = Storage::disk('public')->readStream($this->path);
        if (! is_resource($stream)) {
            return false;
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_equals($this->sha256, hash_final($context));
    }

    public function attachable()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
