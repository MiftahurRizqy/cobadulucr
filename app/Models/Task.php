<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use Auditable;

    protected $fillable = ['task_id', 'title', 'description', 'customer_id', 'opportunity_id', 'created_by', 'reviewer_id', 'due_at', 'priority', 'status', 'checklist', 'completion_note', 'completed_at'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime', 'checklist' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Task $task) => $task->task_id ??= 'TSK-'.now()->format('ym').'-'.str_pad((string) (self::max('id') + 1), 5, '0', STR_PAD_LEFT));
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class)->withPivot('assignment_role')->withTimestamps();
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isMasterAdmin()) {
            return $query;
        }
        if (in_array($user->authority_level, ['manager', 'supervisor'], true)) {
            return $query->whereHas('customer', fn ($q) => $q->visibleTo($user));
        }

        return $query->where(fn ($q) => $q->where('created_by', $user->id)->orWhereHas('assignees', fn ($q) => $q->whereKey($user->id)));
    }
}
