<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class ReportSavedFilter extends Model
{
    use Auditable;
    protected $fillable = ['user_id', 'name', 'filters'];

    protected $casts = ['filters' => 'array'];
}
