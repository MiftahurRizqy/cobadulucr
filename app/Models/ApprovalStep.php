<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalStep extends Model
{
    protected $fillable = ['approval_id', 'approver_id', 'position', 'status', 'note', 'decided_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function approval()
    {
        return $this->belongsTo(Approval::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class);
    }
}
