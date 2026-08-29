<?php

namespace Tests\Feature;

use App\Models\SalesKpiTarget;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KpiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_team_kpi_and_set_sales_target(): void
    {
        $this->seed();
        $manager = User::where('email', 'manager@unified.test')->firstOrFail();
        $sales = User::where('email', 'sales@unified.test')->firstOrFail();
        Opportunity::where('owner_id', $sales->id)->firstOrFail()->update([
            'status' => 'won', 'stage_entered_at' => '2026-08-20 10:00:00',
        ]);

        $this->actingAs($manager)->get(route('kpi.index', ['period' => '2026-08']))
            ->assertOk()->assertSee('KPI Penjualan')->assertSee($sales->name)
            ->assertSee('Rp 420.000.000')->assertSee('Catatan Head / Manager')
            ->assertSee('Kinerja Tim')->assertSee('Head · 2 sales')
            ->assertSee('Download laporan')
            ->assertSeeInOrder(['Nadia Sales', 'Iky Account Executive']);

        $this->actingAs($manager)->get(route('kpi.export.excel', ['period' => '2026-08']))
            ->assertOk()->assertDownload('laporan-kpi-penjualan-2026-08.xlsx');

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
}
