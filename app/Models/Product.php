<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use Auditable;

    protected $fillable = ['sku', 'name', 'category', 'unit', 'base_price', 'is_active'];
}
