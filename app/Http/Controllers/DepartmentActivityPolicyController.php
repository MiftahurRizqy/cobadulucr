<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentActivityPolicyController extends Controller
{
    public function index()
    {
        return view('settings.activity-evidence', [
            'departments' => Department::with('businessUnit')->withCount('users')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'required_department_ids' => ['nullable', 'array'],
            'required_department_ids.*' => ['integer', 'exists:departments,id'],
        ]);

        DB::transaction(function () use ($data) {
            Department::query()->update(['activity_evidence_required' => false]);
            Department::query()
                ->whereKey($data['required_department_ids'] ?? [])
                ->update(['activity_evidence_required' => true]);
        });

        return back()->with('success', 'Kebijakan bukti aktivitas berhasil diperbarui.');
    }
}
