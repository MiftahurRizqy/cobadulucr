<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_admin_can_open_central_audit_log_but_sales_cannot(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('authority_level', 'master_admin')->firstOrFail();
        $sales = User::whereHas('roles', fn ($query) => $query->where('slug', 'sales'))->firstOrFail();

        $this->actingAs($admin)->get(route('audit.index'))->assertOk()->assertSee('Audit Log');
        $this->actingAs($sales)->get(route('audit.index'))->assertForbidden();
    }

    public function test_audit_log_only_stores_changed_fields_and_never_password(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('authority_level', 'master_admin')->firstOrFail();
        $customer = Customer::firstOrFail();
        $originalCompanyName = $customer->company_name;

        $this->actingAs($admin);
        $customer->update(['company_name' => 'Customer Baru']);
        $admin->update(['password' => 'rahasia-baru', 'phone' => '08123456789']);

        $customerLog = AuditLog::where('auditable_type', Customer::class)->where('action', 'updated')->latest()->firstOrFail();
        $this->assertSame(['company_name'], array_keys($customerLog->new_values));
        $this->assertSame($originalCompanyName, $customerLog->old_values['company_name']);

        $userLog = AuditLog::where('auditable_type', User::class)->where('action', 'updated')->latest()->firstOrFail();
        $this->assertArrayNotHasKey('password', $userLog->new_values);
        $this->assertSame('08123456789', $userLog->new_values['phone']);
    }
}
