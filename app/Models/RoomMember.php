<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomMember extends Model
{
    protected $fillable = ['customer_room_id', 'user_id', 'access_level', 'visible_fields', 'invited_by', 'expires_at'];

    protected function casts(): array
    {
        return ['visible_fields' => 'array', 'expires_at' => 'datetime'];
    }

    public function room()
    {
        return $this->belongsTo(CustomerRoom::class, 'customer_room_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
