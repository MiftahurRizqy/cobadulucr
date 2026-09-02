<?php

namespace Tests\Feature;

use App\Models\KpiTemplate;
use App\Models\SalesKpiTarget;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Tests\TestCase;

class KpiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_team_kpi_and_set_sales_target(): void
    {
        $this->seed();
        $manager = User::where('email', 'manager@unified.test')->firstOrFail();
        $sales = User::where('email', 'sales@unified.test')->firstOrFail();
        $telesales = User::where('email', 'telesales@unified.test')->firstOrFail();
        Opportunity::where('owner_id', $sales->id)->firstOrFail()->update([
            'status' => 'won', 'stage_entered_at' => '2026-08-20 10:00:00',
        ]);

        $this->actingAs($manager)->get(route('kpi.index', ['period' => '2026-08']))
            ->assertOk()->assertSee('KPI Penjualan')->assertSee($sales->name)
            ->assertSee('Rp 420.000.000')->assertSee('Catatan Head / Manager')
            ->assertSee('Kinerja Tim')->assertSee('Head · 3 sales')
            ->assertSee('Download laporan')
            ->assertSee($telesales->name)->assertSee('Telesales')
            ->assertSeeInOrder(['Nadia Sales', 'Iky Account Executive']);

        $excelResponse = $this->actingAs($manager)->get(route('kpi.export.excel', ['period' => '2026-08']));
        $excelResponse->assertOk()->assertDownload('laporan-kpi-penjualan-2026-08.xlsx');
        $temporaryExcel = tempnam(sys_get_temp_dir(), 'kpi-export-').'.xlsx';
        file_put_contents($temporaryExcel, $excelResponse->streamedContent());
        try {
            $workbook = IOFactory::load($temporaryExcel);
            $sheet = $workbook->getActiveSheet();
            $values = collect($sheet->rangeToArray('A1:I'.$sheet->getHighestDataRow()))->flatten();

            $this->assertNotSame('UNIFIED CRM', $sheet->getCell('C1')->getValue());
            $this->assertNotEmpty($sheet->getCell('C1')->getValue());
            $this->assertSame(PageSetup::ORIENTATION_PORTRAIT, $sheet->getPageSetup()->getOrientation());
            $this->assertSame(PageSetup::PAPERSIZE_A4, $sheet->getPageSetup()->getPaperSize());
            $this->assertTrue($values->contains('Head / Koordinator'));
            $this->assertTrue($values->contains('Telesales'));
            $this->assertStringContainsString('KPI/202608', $sheet->getHeaderFooter()->getOddFooter());
        } finally {
            @unlink($temporaryExcel);
        }

        $this->actingAs($manager)->get(route('kpi.export.pdf', ['period' => '2026-08']))
            ->assertOk()->assertDownload('laporan-kpi-penjualan-2026-08.pdf');

        $this->actingAs($manager)->put(route('kpi.update', $sales), [
            'period' => '2026-08', 'sales_target' => 'Rp 125.000.000',
            'noo_target' => 40, 'custom_noo_target' => 20,
            'drink_volume_target' => 500000, 'food_volume_target' => 200000,
            'evaluation_notes' => 'Fokus follow-up prospect prioritas.',
        ])->assertRedirect();

        $this->assertDatabaseHas('sales_kpi_targets', [
            'user_id' => $sales->id, 'sales_target' => 125000000,
            'noo_target' => 40, 'custom_noo_target' => 20,
            'drink_volume_target' => 500000, 'food_volume_target' => 200000,
            'updated_by' => $manager->id,
        ]);

        $this->actingAs($sales)->get(route('kpi.index', ['period' => '2026-08']))
            ->assertOk()->assertSee('Commented by')->assertSee($manager->name)
            ->assertSee('Fokus follow-up prospect prioritas.');
    }

    public function test_sales_can_only_view_own_kpi_and_cannot_change_target(): void
    {
        $this->seed();
        $sales = User::where('email', 'sales@unified.test')->firstOrFail();
        $otherSales = User::where('email', 'iky@unified.test')->firstOrFail();

        $this->actingAs($sales)->get(route('kpi.index'))->assertOk()
            ->assertSee($sales->name)->assertDontSee($otherSales->name);

        $this->actingAs($sales)->put(route('kpi.update', $sales), [
            'period' => '2026-08', 'sales_target' => 1,
        ])->assertForbidden();

        $this->assertSame(0, SalesKpiTarget::count());
    }

    public function test_custom_noo_is_counted_from_custom_items_in_any_pipeline(): void
    {
        $this->seed();
        $manager = User::where('email', 'admin@unified.test')->firstOrFail();
        $sales = User::where('email', 'sales@unified.test')->firstOrFail();
        $pipeline = Pipeline::create(['name' => 'Pipeline Pesanan Campuran', 'slug' => 'mixed-kpi', 'created_by' => $manager->id]);
        $stage = PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => 'New', 'slug' => 'new', 'position' => 1, 'probability' => 10]);
        $customer = Customer::create([
            'company_name' => 'Customer Item Custom', 'phone' => '081200000009',
            'sales_owner_id' => $sales->id, 'created_by' => $manager->id,
            'became_customer_at' => '2026-09-01 09:00:00',
        ]);
        $opportunity = Opportunity::create([
            'customer_id' => $customer->id, 'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id, 'owner_id' => $sales->id,
            'title' => 'Pesanan campuran', 'status' => 'open', 'probability' => 10,
        ]);
        $opportunity->items()->createMany([
            ['product_name' => 'Produk Reguler', 'product_type' => 'regular', 'quantity' => 10, 'quantity_unit' => 'pcs'],
            ['product_name' => 'Produk Custom', 'product_type' => 'custom', 'custom_specification' => 'Kemasan berlogo', 'quantity' => 1, 'quantity_unit' => 'pcs'],
        ]);

        $this->actingAs($manager)->get(route('kpi.index', ['period' => '2026-09']))
            ->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows
                ->firstWhere('sales.id', $sales->id)?->customNoo === 1);
    }

    public function test_kpi_manage_permission_can_be_revoked_from_manager_role(): void
    {
        $this->seed();
        $manager = User::where('email', 'manager@unified.test')->firstOrFail();
        $sales = User::where('email', 'sales@unified.test')->firstOrFail();
        $permission = Permission::where('key', 'kpi.manage')->firstOrFail();
        Role::where('slug', 'sales_manager')->firstOrFail()->deniedPermissions()->syncWithoutDetaching($permission->id);

        $this->actingAs($manager)->get(route('kpi.index', ['period' => '2026-08']))
            ->assertOk()->assertDontSee('Atur</button>', false);

        $this->actingAs($manager)->put(route('kpi.update', $sales), [
            'period' => '2026-08', 'sales_target' => 1000000,
        ])->assertForbidden();

        $this->assertSame(0, SalesKpiTarget::count());
    }

    public function test_kpi_can_be_viewed_and_exported_for_a_month_range(): void
    {
        $this->seed();
        $manager = User::where('email', 'manager@unified.test')->firstOrFail();
        $sales = User::where('email', 'sales@unified.test')->firstOrFail();

        SalesKpiTarget::create([
            'user_id' => $sales->id, 'period_start' => '2026-06-01', 'period_end' => '2026-06-30',
            'sales_target' => 10000000, 'updated_by' => $manager->id,
        ]);
        SalesKpiTarget::create([
            'user_id' => $sales->id, 'period_start' => '2026-07-01', 'period_end' => '2026-07-31',
            'sales_target' => 20000000, 'updated_by' => $manager->id,
        ]);

        $range = ['start_period' => '2026-06', 'end_period' => '2026-08'];
        $this->actingAs($manager)->get(route('kpi.index', $range))
            ->assertOk()->assertSee('01 Jun 2026 – 31 Agt 2026')->assertSee('Rp 30.000.000')
            ->assertSee('Tahun lalu')->assertDontSee('Atur</button>', false);

        $this->actingAs($manager)->get(route('kpi.export.excel', $range))
            ->assertOk()->assertDownload('laporan-kpi-penjualan-2026-06-01_sampai_2026-08-31.xlsx');
        $this->actingAs($manager)->get(route('kpi.export.pdf', $range))
            ->assertOk()->assertDownload('laporan-kpi-penjualan-2026-06-01_sampai_2026-08-31.pdf');
    }

    public function test_manager_can_save_and_apply_role_template_without_overwriting_existing_targets_by_default(): void
    {
        $this->seed();
        $manager = User::where('email', 'manager@unified.test')->firstOrFail();
        $sales = User::where('email', 'sales@unified.test')->firstOrFail();
        SalesKpiTarget::create([
            'user_id' => $sales->id, 'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
            'sales_target' => 999, 'updated_by' => $manager->id,
        ]);

        $values = [
            'sales_target' => 'Rp 50.000.000', 'noo_target' => 40, 'custom_noo_target' => 20,
            'drink_volume_target' => 500000, 'food_volume_target' => 200000,
        ];
        $this->actingAs($manager)->put(route('kpi.templates.update', 'sales'), $values)->assertRedirect();
        $this->assertDatabaseHas('kpi_templates', ['role_slug' => 'sales', 'sales_target' => 50000000]);

        $this->actingAs($manager)->post(route('kpi.templates.apply', 'sales'), ['period' => '2026-09'])->assertRedirect();
        $this->assertSame('999.00', SalesKpiTarget::where('user_id', $sales->id)->whereDate('period_start', '2026-09-01')->firstOrFail()->sales_target);

        $this->actingAs($manager)->post(route('kpi.templates.apply', 'sales'), ['period' => '2026-09', 'overwrite' => 1])->assertRedirect();
        $target = SalesKpiTarget::where('user_id', $sales->id)->whereDate('period_start', '2026-09-01')->firstOrFail();
        $this->assertSame('50000000.00', $target->sales_target);
        $this->assertSame(40, $target->noo_target);
        $this->assertSame(20, $target->custom_noo_target);
    }

    public function test_new_sales_receives_current_month_role_template(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@unified.test')->firstOrFail();
        $manager = User::where('email', 'manager@unified.test')->firstOrFail();
        $salesRole = Role::where('slug', 'sales')->firstOrFail();
        KpiTemplate::create([
            'role_slug' => 'sales', 'sales_target' => 75000000, 'noo_target' => 30,
            'custom_noo_target' => 10, 'drink_volume_target' => 250000,
            'food_volume_target' => 100000, 'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Sales Template Baru', 'email' => 'sales.template@example.test',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'user_type' => 'frontliner', 'manager_id' => $manager->id,
            'is_active' => 1, 'role_ids' => [$salesRole->id],
        ])->assertRedirect(route('users.index'));

        $newSales = User::where('email', 'sales.template@example.test')->firstOrFail();
        $target = SalesKpiTarget::where('user_id', $newSales->id)->whereDate('period_start', now()->startOfMonth())->firstOrFail();
        $this->assertSame('75000000.00', $target->sales_target);
        $this->assertSame(30, $target->noo_target);
    }
}
