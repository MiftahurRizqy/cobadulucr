<?php

namespace Tests\Feature;

use App\Http\Middleware\IdentifyTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_is_initialized_before_authentication_and_route_binding(): void
    {
        app(\Illuminate\Contracts\Http\Kernel::class);
        $priority = app('router')->middlewarePriority;

        $this->assertLessThan(
            array_search(AuthenticatesRequests::class, $priority, true),
            array_search(IdentifyTenant::class, $priority, true),
        );
        $this->assertLessThan(
            array_search(\Illuminate\Routing\Middleware\SubstituteBindings::class, $priority, true),
            array_search(IdentifyTenant::class, $priority, true),
        );
    }

    public function test_login_requires_company_and_stores_selected_tenant(): void
    {
        $tenant = Tenant::query()->where('slug', 'wiguna')->firstOrFail();
        $user = User::factory()->create(['email' => 'admin@wiguna.test', 'password' => 'password']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('tenant_id');

        $this->post(route('login.store'), [
            'tenant_id' => $tenant->id, 'email' => $user->email, 'password' => 'password',
        ])->assertRedirect(route('dashboard'))->assertSessionHas('tenant_id', $tenant->id);
    }

    public function test_login_lists_active_companies_and_hides_inactive_companies(): void
    {
        Tenant::create(['name' => 'PT Aktif Baru', 'slug' => 'aktif-baru', 'database_name' => 'crm_aktif_baru', 'is_active' => true]);
        Tenant::create(['name' => 'PT Nonaktif', 'slug' => 'nonaktif', 'database_name' => 'crm_nonaktif', 'is_active' => false]);

        $this->get(route('login'))->assertOk()
            ->assertSee('PT Wiguna Inti Batara Utama')
            ->assertSee('PT Aktif Baru')
            ->assertDontSee('PT Nonaktif');
    }

    public function test_platform_admin_can_update_company_name_color_and_logo(): void
    {
        Storage::fake('public');
        $this->seed();
        $tenant = Tenant::where('slug', 'wiguna')->firstOrFail();
        $admin = User::where('email', 'admin@unified.test')->firstOrFail();

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])->patch(route('tenants.update', $tenant), [
            'name' => 'PT Wiguna Baru',
            'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
        ])->assertRedirect()->assertSessionHas('success');

        $tenant->refresh();
        $this->assertSame('PT Wiguna Baru', $tenant->name);
        $this->assertSame('#4f46e5', $tenant->primary_color);
        $this->assertNotNull($tenant->logo_path);
        Storage::disk('public')->assertExists($tenant->logo_path);
    }

    public function test_company_form_hides_technical_identifiers_from_admin(): void
    {
        $this->seed();
        $tenant = Tenant::where('slug', 'wiguna')->firstOrFail();
        $admin = User::where('email', 'admin@unified.test')->firstOrFail();

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->get(route('tenants.index'))
            ->assertOk()
            ->assertDontSee('Nama database')
            ->assertDontSee('Kode *')
            ->assertSee('Logo perusahaan')
            ->assertSee('database terpisah, dan akun administrator dibuat otomatis');
    }

    public function test_other_company_admin_can_open_creation_but_cannot_manage_another_company(): void
    {
        $this->seed();
        $wiguna = Tenant::where('slug', 'wiguna')->firstOrFail();
        $sanpota = Tenant::create(['name' => 'PT Sanpota Test', 'slug' => 'sanpota-test', 'database_name' => 'crm_sanpota_test', 'is_active' => true]);
        $admin = User::where('authority_level', 'master_admin')->firstOrFail();
        $this->actingAs($admin)->withSession(['tenant_id' => $sanpota->id]);
        $this->get(route('tenants.index'))->assertOk()->assertSee('Tambah perusahaan')
            ->assertDontSee($wiguna->name)->assertSee($sanpota->name);
        // Validation is reached rather than rejecting non-Wiguna administrators.
        $this->post(route('tenants.store'), [])->assertSessionHasErrors(['name', 'admin_email']);
        $this->patch(route('tenants.update', $wiguna), ['name' => 'Unauthorized'])->assertForbidden();
        $this->patch(route('tenants.toggle', $wiguna))->assertForbidden();
        $this->patch(route('tenants.complete-setup', $wiguna), [])->assertForbidden();
        $this->assertSame($wiguna->name, $wiguna->fresh()->name);
        $this->patch(route('tenants.update', $sanpota), ['name' => 'Own Company'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('Own Company', $sanpota->fresh()->name);
        $this->withSession(['tenant_id' => $wiguna->id])->patch(route('tenants.update', $sanpota), ['name' => 'Unauthorized'])->assertForbidden();
        $sales = User::whereHas('roles', fn ($roles) => $roles->where('slug', 'sales'))->firstOrFail();
        $this->actingAs($sales)->get(route('tenants.index'))->assertForbidden();
        $this->post(route('tenants.store'), [])->assertForbidden();
    }
}
