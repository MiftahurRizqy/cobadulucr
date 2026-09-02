<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Collection;

class CustomerDuplicateDetector
{
    public function detect(User $user, array $input, ?Customer $exceptCustomer = null, ?Lead $exceptLead = null): Collection
    {
        $phone = $this->digits($input['phone'] ?? null);
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $npwp = $this->digits($input['npwp'] ?? null);
        $brand = $this->text($input['brand_name'] ?? null);
        $company = $this->text($input['company_name'] ?? null);

        $customers = Customer::query()->visibleTo($user)
            ->when($exceptCustomer, fn ($query) => $query->whereKeyNot($exceptCustomer->id))
            ->get(['id', 'customer_id', 'company_name', 'brand_name', 'phone', 'email', 'npwp']);
        $leads = $user->canAccess('leads.view')
            ? Lead::query()->visibleTo($user)->where('status', '!=', 'converted')
                ->when($exceptLead, fn ($query) => $query->whereKeyNot($exceptLead->id))
                ->get(['id', 'lead_id', 'company_name', 'brand_name', 'phone', 'email'])
            : collect();

        // Convert the Eloquent collections to base collections before combining
        // them. EloquentCollection::merge() expects model instances and crashes
        // when the mapped values are match-result arrays (or null).
        return $customers->toBase()
            ->map(fn ($record) => $this->match($record, 'Customer', $phone, $email, $npwp, $brand, $company))
            ->concat($leads->toBase()->map(fn ($record) => $this->match($record, 'Lead', $phone, $email, null, $brand, $company)))
            ->filter()
            ->sortByDesc('score')
            ->take(8)
            ->values();
    }

    private function match($record, string $type, string $phone, string $email, ?string $npwp, string $brand, string $company): ?array
    {
        $reasons = [];
        $score = 0;
        if ($phone !== '' && $phone === $this->digits($record->phone)) { $reasons[] = 'Nomor WhatsApp sama'; $score = 100; }
        if ($email !== '' && $email === mb_strtolower(trim((string) $record->email))) { $reasons[] = 'Email sama'; $score = 100; }
        if ($npwp && $npwp === $this->digits($record->npwp ?? null)) { $reasons[] = 'NPWP sama'; $score = 100; }

        $existingBrand = $this->text($record->brand_name);
        if ($brand !== '' && $existingBrand !== '') {
            similar_text($brand, $existingBrand, $similarity);
            if ($similarity >= 82) {
                $reasons[] = $similarity >= 98 ? 'Nama brand sama' : 'Nama brand mirip';
                $score = max($score, (int) round($similarity));
            }
        }

        $existingCompany = $this->text($record->company_name);
        if ($company !== '' && $existingCompany !== '') {
            similar_text($company, $existingCompany, $similarity);
            if ($similarity >= 82) {
                $reasons[] = $similarity >= 98 ? 'Nama perusahaan sama' : 'Nama perusahaan mirip';
                $score = max($score, (int) round($similarity));
            }
        }

        if ($reasons === []) return null;
        return [
            'type' => $type,
            'id' => $record->id,
            'code' => $record->customer_id ?? $record->lead_id,
            'name' => $record->brand_name ?: $record->company_name,
            'reasons' => array_values(array_unique($reasons)),
            'score' => $score,
        ];
    }

    private function digits($value): string { return preg_replace('/\D+/', '', (string) $value); }
    private function text($value): string { return trim(preg_replace('/\s+/', ' ', mb_strtolower((string) $value))); }
}
