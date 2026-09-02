<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantProvisioningSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_passwords_are_not_flashed_on_validation_failure(): void
    {
        $this->seed();
        $admin = User::where('authority_level', 'master_admin')->firstOrFail();
        $tenant = Tenant::where('slug', 'wiguna')->firstOrFail();
        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->post(route('tenants.store'), [
                'name' => 'Validation Test', 'admin_email' => 'invalid',
                'admin_password' => 'private-test-password', 'admin_password_confirmation' => 'private-test-password',
            ])->assertSessionHasErrors('admin_email')
            ->assertSessionMissing('_old_input.admin_password')
            ->assertSessionMissing('_old_input.admin_password_confirmation')
            ->assertSessionHas('_old_input.name', 'Validation Test');
    }

    public function test_failed_migration_stops_bootstrap_and_restores_original_tenant_without_flashing_secrets(): void
    {
        $this->seed();
        $tenant = Tenant::where('slug', 'wiguna')->firstOrFail();
        $admin = User::where('authority_level', 'master_admin')->firstOrFail();
        $userCount = User::count();
        // Mock DDL only: never create or drop a real MySQL database during tests.
        $central = \Mockery::mock();
        $central->shouldReceive('statement')->once()->with('CREATE DATABASE `crm_migration_failure` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')->andReturn(true);
        $central->shouldReceive('statement')->once()->with('DROP DATABASE IF EXISTS `crm_migration_failure`')->andReturn(true);
        DB::partialMock()->shouldReceive('connection')->with('central')->andReturn($central);
        Artisan::shouldReceive('call')->once()->with('migrate', ['--database' => 'tenant', '--force' => true])->andReturn(1);

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])->post(route('tenants.store'), [
            'name' => 'Migration Failure', 'admin_name' => 'New Admin', 'admin_email' => 'new@example.test',
            'admin_password' => 'private-test-password', 'admin_password_confirmation' => 'private-test-password',
        ])->assertSessionHasErrors('name')->assertSessionMissing('success')
            ->assertSessionMissing('_old_input.admin_password')
            ->assertSessionMissing('_old_input.admin_password_confirmation')
            ->assertSessionHas('_old_input.admin_name', 'New Admin');
        $this->assertFalse(Tenant::where('slug', 'migration-failure')->exists());
        $this->assertSame($userCount, User::count());
        $this->assertSame($tenant->id, app(TenantManager::class)->current()->id);
    }
}
