<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Support\BusinessUnitResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerTypeSettingController extends Controller
{
    public function index(Request $request, BusinessUnitResolver $resolver): View
    {
        $this->authorizeMasterAdmin($request);

        $customerTypes = $resolver->managedOptions()->map(function (BusinessUnit $customerType) {
            $customerType->usage_count = $this->usageCount($customerType);

            return $customerType;
        });

        return view('settings.customer-types', compact('customerTypes'));
    }

    public function store(Request $request, BusinessUnitResolver $resolver): RedirectResponse
    {
        $this->authorizeMasterAdmin($request);
        $name = trim($request->validate(['name' => ['required', 'string', 'max:120']])['name']);

        if (BusinessUnit::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            throw ValidationException::withMessages(['name' => 'Jenis customer tersebut sudah tersedia.']);
        }

        $resolver->resolve($name, null, true);

        return back()->with('success', 'Jenis customer berhasil ditambahkan.');
    }

    public function update(Request $request, BusinessUnit $customerType): RedirectResponse
    {
        $this->authorizeMasterAdmin($request);
        $name = trim($request->validate(['name' => ['required', 'string', 'max:120']])['name']);

        if (BusinessUnit::whereKeyNot($customerType->id)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            throw ValidationException::withMessages(['name' => 'Jenis customer tersebut sudah tersedia.']);
        }

        $oldName = $customerType->name;
        DB::transaction(function () use ($customerType, $oldName, $name) {
            $customerType->update(['name' => $name]);
            Lead::where('business_unit_id', $customerType->id)->orWhere('business_type', $oldName)->update(['business_type' => $name]);
            Customer::where('business_unit_id', $customerType->id)->orWhere('business_type', $oldName)->update(['business_type' => $name]);
            Pipeline::where('business_unit_id', $customerType->id)->orWhere('business_type', $oldName)->update(['business_type' => $name]);
        });

        return back()->with('success', 'Jenis customer berhasil diperbarui.');
    }

    public function toggle(Request $request, BusinessUnit $customerType): RedirectResponse
    {
        $this->authorizeMasterAdmin($request);
        $customerType->update(['is_active' => ! $customerType->is_active]);

        return back()->with('success', $customerType->is_active
            ? 'Jenis customer kembali diaktifkan.'
            : 'Jenis customer dinonaktifkan dan tidak lagi muncul pada form baru.');
    }

    public function destroy(Request $request, BusinessUnit $customerType): RedirectResponse
    {
        $this->authorizeMasterAdmin($request);

        if ($this->usageCount($customerType) > 0) {
            return back()->with('error', 'Jenis customer sudah digunakan. Nonaktifkan agar data lama tetap aman.');
        }

        $customerType->delete();

        return back()->with('success', 'Jenis customer yang belum digunakan berhasil dihapus.');
    }

    private function usageCount(BusinessUnit $customerType): int
    {
        return Lead::where('business_unit_id', $customerType->id)->orWhere('business_type', $customerType->name)->count()
            + Customer::where('business_unit_id', $customerType->id)->orWhere('business_type', $customerType->name)->count()
            + Pipeline::where('business_unit_id', $customerType->id)->orWhere('business_type', $customerType->name)->count()
            + Department::where('business_unit_id', $customerType->id)->count()
            + $customerType->users()->count();
    }

    private function authorizeMasterAdmin(Request $request): void
    {
        abort_unless($request->user()->isMasterAdmin(), 403);
    }
}
