<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Support\CustomerDuplicateDetector;
use Illuminate\Http\Request;

class CustomerDuplicateController extends Controller
{
    public function __invoke(Request $request, CustomerDuplicateDetector $detector)
    {
        $data = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'npwp' => ['nullable', 'string', 'max:50'],
            'except_customer' => ['nullable', 'integer'],
            'except_lead' => ['nullable', 'integer'],
        ]);

        return response()->json([
            'matches' => $detector->detect(
                $request->user(),
                $data,
                filled($data['except_customer'] ?? null) ? Customer::find($data['except_customer']) : null,
                filled($data['except_lead'] ?? null) ? Lead::find($data['except_lead']) : null,
            ),
        ]);
    }
}
