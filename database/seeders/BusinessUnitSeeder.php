<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use Illuminate\Database\Seeder;

class BusinessUnitSeeder extends Seeder
{
    public const UNITS = [
        'MCF' => 'Multi Chain & Franchise',
        'RRM' => 'Resto / Rumah Makan',
        'CJB' => 'Cafe, Jus Bar, Matcha House & Bar',
        'FSB' => 'Food Stall, Beverage Stall & Food Court',
        'HTL' => 'Hotel',
        'CTR' => 'Catering',
        'DST' => 'Distributor',
        'TPK' => 'Toko Plastik & Kemasan',
        'TBK' => 'Toko Bahan Kue',
        'TML' => 'Toko Madura / Lapak',
        'TOA' => 'Toko Obat / Apotek',
        'THP' => 'Toko Hewan / Pet Shop',
        'TKC' => 'Toko Kecantikan',
        'TSB' => 'Toko Sembako & Beras',
        'FRI' => 'Fresh Industry',
        'FOI' => 'Food Industry',
        'NFI' => 'Non Food Industry',
        'MTN' => 'Modern Trade Nasional',
        'MTL' => 'Modern Trade Lokal',
        'OTH' => 'Other',
    ];

    public const RETIRED_DEFAULTS = [
        'Food & Beverage',
        'Creative Projects',
        'Coffee Shop & Matcha Shop',
        'Restaurant & Cloud Kitchen',
        'Bakery & Dessert',
        'Catering & Food Service',
        'Franchise & Multi Outlet',
        'Distributor & Reseller',
        'Food Industry & Factory',
    ];

    public function run(): void
    {
        BusinessUnit::query()->whereIn('name', self::RETIRED_DEFAULTS)->update(['is_active' => false]);

        foreach (self::UNITS as $code => $name) {
            BusinessUnit::query()->updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }
    }
}
