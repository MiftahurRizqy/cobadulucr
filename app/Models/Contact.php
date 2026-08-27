<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = ['customer_id', 'name', 'position', 'phone', 'whatsapp', 'email', 'is_primary', 'notes'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'notes' => 'array'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
