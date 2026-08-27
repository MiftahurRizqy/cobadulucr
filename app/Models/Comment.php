<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $fillable = ['commentable_type', 'commentable_id', 'user_id', 'body', 'mentioned_user_ids'];

    protected function casts(): array
    {
        return ['mentioned_user_ids' => 'array'];
    }

    public function commentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
