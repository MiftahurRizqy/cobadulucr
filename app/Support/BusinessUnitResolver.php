<?php

namespace App\Support;

use App\Models\BusinessUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BusinessUnitResolver
{
    private const LEGACY_NAMES = [
        'Food & Beverage', 'Creative Projects', 'Coffee Shop & Matcha Shop',
        'Restaurant & Cloud Kitchen', 'Bakery & Dessert', 'Catering & Food Service',
        'Franchise & Multi Outlet', 'Distributor & Reseller', 'Food Industry & Factory',
    ];

    public function options(): Collection
    {
        return BusinessUnit::query()
            ->where('is_active', true)
            ->whereNotIn('name', self::LEGACY_NAMES)
            ->whereRaw('LOWER(name) <> ?', ['other'])
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    public function managedOptions(): Collection
    {
        return BusinessUnit::query()
            ->whereNotIn('name', self::LEGACY_NAMES)
            ->whereRaw('LOWER(name) <> ?', ['other'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_active']);
    }

    public function resolve(?string $selectedName, ?string $customName = null, bool $allowCreate = true): ?BusinessUnit
    {
        $selectedName = trim((string) $selectedName);
        $customName = trim((string) $customName);
        $name = strcasecmp($selectedName, 'Other') === 0 && $customName !== ''
            ? $customName
            : $selectedName;

        if ($name === '') {
            return null;
        }

        $existing = BusinessUnit::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            if (! $existing->is_active) {
                if (! $allowCreate) {
                    throw ValidationException::withMessages(['business_type' => 'Jenis customer yang dipilih sudah tidak aktif.']);
                }
                $existing->update(['is_active' => true]);
            }

            return $existing;
        }

        if (! $allowCreate) {
            throw ValidationException::withMessages(['business_type' => 'Jenis customer baru hanya dapat ditambahkan oleh Master Admin.']);
        }

        $baseCode = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: 'BU', 0, 8));
        $code = $baseCode;
        $suffix = 2;

        while (BusinessUnit::where('code', $code)->exists()) {
            $code = Str::substr($baseCode, 0, max(1, 8 - strlen((string) $suffix))).$suffix++;
        }

        return BusinessUnit::create([
            'code' => $code,
            'name' => $name,
            'is_active' => true,
        ]);
    }
}
