<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AreaController extends Controller
{
    public function index(Request $request)
    {
        $areas = Area::query()
            ->withCount(['users', 'customers', 'leads'])
            ->when($request->search, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('branch', 'like', "%{$search}%")))
            ->when($request->status !== null && $request->status !== '', fn ($query) => $query->where('is_active', $request->boolean('status')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('areas.index', compact('areas'));
    }

    public function create()
    {
        return view('areas.form', ['area' => new Area]);
    }

    public function store(Request $request)
    {
        Area::create($this->validated($request));

        return redirect()->route('areas.index')->with('success', 'Area berhasil ditambahkan.');
    }

    public function edit(Area $area)
    {
        return view('areas.form', compact('area'));
    }

    public function update(Request $request, Area $area)
    {
        $area->update($this->validated($request, $area));

        return redirect()->route('areas.index')->with('success', 'Area berhasil diperbarui.');
    }

    private function validated(Request $request, ?Area $area = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('areas', 'code')->ignore($area)],
            'name' => ['required', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
