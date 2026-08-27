<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DuplicateAndProductHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_check_finds_normalized_contact_and_similar_company(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin', 'is_active' => true]);
        Customer::create([
            'company_name' => 'PT Sinar Hospitality',
            'phone' => '0812-3456-7890',
            'email' => 'sales@sinar.test',
            'npwp' => '12.345.678.9-012.000',
            'sales_owner_id' => $admin->id,
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->getJson(route('customers.duplicate-check', [
            'company_name' => 'PT Sinar Hospitaliti',
            'phone' => '081234567890',
        ]))->assertOk()->assertJsonPath('matches.0.name', 'PT Sinar Hospitality');
    }

    public function test_product_changes_record_actor_and_changed_values(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('authority_level', 'master_admin')->firstOrFail();
        $opportunity = Opportunity::with('items')->firstOrFail();
        $item = $opportunity->items->firstOrFail();

        $this->actingAs($admin)->patch(route('opportunities.items.update', [$opportunity, $item]), [
            'product_name' => $item->product_name,
            'quantity' => $item->quantity + 7,
            'quantity_unit' => $item->quantity_unit,
            'target_price' => $item->target_price,
            'unit_price' => $item->unit_price,
            'photo' => UploadedFile::fake()->image('produk.jpg'),
        ])->assertRedirect();

        $log = AuditLog::where('auditable_type', OpportunityItem::class)
            ->where('auditable_id', $item->id)->where('action', 'updated')->latest()->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($item->quantity + 7, (int) data_get($log->new_values, 'quantity'));
    }
}
