<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Pipeline;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class KanbanOpportunitySeeder extends Seeder
{
    public function run(): void
    {
        $pipeline = Pipeline::query()
            ->where('slug', 'horeka')
            ->with('stages')
            ->firstOrFail();

        $stages = $pipeline->stages->values();
        $customers = Customer::query()->whereNotNull('sales_owner_id')->get();
        $plasticProducts = collect([
            ['sku' => 'PLS-001', 'name' => 'Paper Bowl 650 ML', 'category' => 'Paper Bowl', 'unit' => 'pcs', 'base_price' => 1650],
            ['sku' => 'PLS-002', 'name' => 'Paper Cup 5 OZ DPE', 'category' => 'Paper Cup', 'unit' => 'pcs', 'base_price' => 475],
            ['sku' => 'PLS-003', 'name' => 'PPI Thinwall Bento 4 Sekat Flat V', 'category' => 'Thinwall', 'unit' => 'pcs', 'base_price' => 2850],
            ['sku' => 'PLS-004', 'name' => 'Seal Cup POT12 Drink Pota', 'category' => 'Cup Sealer', 'unit' => 'pcs', 'base_price' => 390],
            ['sku' => 'PLS-005', 'name' => 'Cup PET 12 OZ', 'category' => 'PET Cup', 'unit' => 'pcs', 'base_price' => 825],
            ['sku' => 'PLS-006', 'name' => 'Cup PET 16 OZ', 'category' => 'PET Cup', 'unit' => 'pcs', 'base_price' => 975],
            ['sku' => 'PLS-007', 'name' => 'Thinwall 500 ML Round', 'category' => 'Thinwall', 'unit' => 'pcs', 'base_price' => 1350],
            ['sku' => 'PLS-008', 'name' => 'Cup PP 12 OZ Oval', 'category' => 'PP Cup', 'unit' => 'pcs', 'base_price' => 850],
            ['sku' => 'PLS-009', 'name' => 'Sumpit Bambu 5 MM', 'category' => 'Cutlery', 'unit' => 'pack', 'base_price' => 18500],
            ['sku' => 'PLS-010', 'name' => 'Cup PET 12 OZ Oval', 'category' => 'PET Cup', 'unit' => 'pcs', 'base_price' => 875],
            ['sku' => 'PLS-011', 'name' => 'Cup PP 16 OZ Oval', 'category' => 'PP Cup', 'unit' => 'pcs', 'base_price' => 1050],
            ['sku' => 'PLS-012', 'name' => 'Lid Dome PET Cup 12 OZ', 'category' => 'Cup Lid', 'unit' => 'pcs', 'base_price' => 425],
        ])->map(fn (array $product) => Product::query()->updateOrCreate(
            ['sku' => $product['sku']],
            [...$product, 'is_active' => true]
        ));

        // Produk bawaan lama ikut disesuaikan agar seluruh data awal konsisten sebagai bisnis kemasan plastik.
        collect([
            'PRD-001' => ['Paper Bowl 650 ML', 'Paper Bowl', 'pcs', 1650],
            'PRD-002' => ['Paper Cup 5 OZ DPE', 'Paper Cup', 'pcs', 475],
            'PRD-003' => ['PPI Thinwall Bento 4 Sekat Flat V', 'Thinwall', 'pcs', 2850],
        ])->each(function (array $data, string $sku): void {
            $product = Product::query()->where('sku', $sku)->first();
            if (!$product) return;
            $product->update(['name' => $data[0], 'category' => $data[1], 'unit' => $data[2], 'base_price' => $data[3]]);
            Opportunity::query()->where('product_id', $product->id)->update(['product_name' => $data[0], 'quantity_unit' => $data[2]]);
            OpportunityItem::query()->where('product_id', $product->id)->update(['product_name' => $data[0], 'quantity_unit' => $data[2]]);
        });

        $products = $plasticProducts;
        $owners = User::query()->whereHas('roles', fn ($query) => $query->whereIn('slug', ['sales', 'telesales', 'csa']))->get();

        if ($stages->isEmpty() || $customers->isEmpty() || $products->isEmpty() || $owners->isEmpty()) {
            $this->command?->warn('Data pipeline, stage, customer, product, atau sales belum tersedia.');
            return;
        }

        $titles = [
            'Kemasan takeaway untuk pembukaan cabang baru',
            'Pengadaan paper cup untuk jaringan coffee shop',
            'Amenities kamar untuk hotel butik',
            'Packaging frozen food edisi premium',
            'Kemasan katering untuk kontrak perusahaan',
            'Redesign box pastry untuk kampanye musiman',
            'Supply Cup PP 12 OZ Oval untuk minuman',
            'Paket amenities untuk renovasi kamar hotel',
            'Kemasan delivery untuk menu keluarga',
            'Paper bag custom untuk gerai bakery',
            'Pengadaan lunch box untuk acara tahunan',
            'Kemasan kopi retail dengan desain baru',
            'Supply cup dan lid untuk ekspansi outlet',
            'Packaging hampers untuk periode akhir tahun',
            'Kemasan saus untuk produk retail',
            'Amenity set untuk properti hospitality baru',
            'Box makanan untuk program corporate catering',
            'Kemasan dessert untuk kolaborasi brand',
            'Supply Cup PET 12 OZ Oval untuk outlet baru',
            'Packaging khusus untuk menu seasonal',
            'Paper bowl untuk layanan pesan antar',
            'Kotak premium untuk produk signature',
            'Kemasan praktis untuk outlet cloud kitchen',
            'Supply packaging untuk pembukaan franchise',
            'Kemasan hotel untuk kebutuhan high season',
            'Box catering untuk kontrak event organizer',
            'Packaging minuman untuk konsep gerai baru',
            'Kemasan take home untuk restoran keluarga',
            'Supply paper bag untuk jaringan restoran',
            'Kemasan produk oleh-oleh edisi terbaru',
        ];

        // Dibuat lebih padat pada stage aktif agar scroll vertikal Kanban mudah diuji.
        $stagePattern = [0, 0, 0, 0, 0, 1, 1, 1, 1, 2, 2, 2, 2, 3, 3, 3, 3, 3, 4, 4, 4, 4, 5, 5, 5, 6, 6, 7, 7, 7];

        Opportunity::query()
            ->where('pipeline_id', $pipeline->id)
            ->whereIn('title', [
                'Supply food container ramah lingkungan',
                'Supply container untuk central kitchen',
            ])
            ->get()
            ->each->delete();

        foreach ($titles as $index => $title) {
            $stage = $stages[$stagePattern[$index] % $stages->count()];
            $customer = $customers[$index % $customers->count()];
            $product = $products[$index % $products->count()];
            $owner = $owners[$index % $owners->count()];
            $quantity = 250 + (($index + 1) * 175);
            $unitPrice = (float) $product->base_price;
            $value = $quantity * $unitPrice;

            $opportunity = Opportunity::query()->updateOrCreate(
                ['title' => $title, 'pipeline_id' => $pipeline->id],
                [
                    'customer_id' => $customer->id,
                    'pipeline_stage_id' => $stage->id,
                    'owner_id' => $owner->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'estimated_quantity' => $quantity,
                    'quantity_unit' => $product->unit ?: 'pcs',
                    'estimated_value' => $value,
                    'probability' => $stage->probability,
                    'target_price' => $unitPrice,
                    'offered_price' => $unitPrice,
                    'expected_close_date' => now()->addDays(7 + $index),
                    'next_action' => 'Follow-up kebutuhan dan keputusan customer',
                    'next_follow_up_at' => now()->addDays(($index % 7) + 1),
                    'priority' => ['low', 'medium', 'high'][$index % 3],
                    'status' => $stage->is_won ? 'won' : ($stage->is_lost ? 'lost' : 'open'),
                    'stage_entered_at' => now()->subDays($index % 16),
                ]
            );

            $opportunity->items()->delete();
            $opportunity->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'quantity_unit' => $product->unit ?: 'pcs',
                'target_price' => $unitPrice,
                'unit_price' => $unitPrice,
                'subtotal' => $value,
            ]);
        }

        $this->command?->info('30 opportunity demo Kanban berhasil dibuat dan disebar ke beberapa stage.');
    }
}
