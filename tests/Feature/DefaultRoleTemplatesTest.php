<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\DefaultRoleTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultRoleTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_templates_fill_missing_roles_without_overwriting_existing_access(): void
    {
        $existing = Role::where('slug', 'master_admin')->firstOrFail();
        $before = $existing->permissions()->pluck('permissions.id')->all();
        $users = User::count();
        $this->assertSame(8, app(DefaultRoleTemplates::class)->apply());
        $this->assertSame(9, Role::count());
        $this->assertSame(0, app(DefaultRoleTemplates::class)->apply());
        $this->assertSame($before, $existing->permissions()->pluck('permissions.id')->all());
        $this->assertSame($users, User::count());
        $sales = Role::where('slug', 'sales')->firstOrFail();
        $this->assertFalse($sales->permissions()->whereIn('key', ['kpi.manage', 'approvals.decide', 'admin.manage'])->exists());
        $sales->permissions()->detach();
        app(DefaultRoleTemplates::class)->apply();
        $this->assertSame(0, $sales->permissions()->count());
    }

    public function test_only_admin_can_apply_templates_and_duplicate_feature_is_removed(): void
    {
        $this->seed();
        $admin = User::where('authority_level', 'master_admin')->firstOrFail();
        $this->actingAs($admin)->get(route('roles.index'))->assertOk()
            ->assertDontSee('Lengkapi role bawaan')->assertDontSee('Duplikat role');
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('roles.duplicate'));
        $this->actingAs($admin)->post('/roles/1/duplicate')->assertNotFound();
        $this->actingAs($admin)->post(route('roles.apply-templates'))->assertRedirect()->assertSessionHas('success');
        $sales = User::whereHas('roles', fn ($query) => $query->where('slug', 'sales'))->firstOrFail();
        $this->actingAs($sales)->post(route('roles.apply-templates'))->assertForbidden();
    }

    public function test_template_button_disappears_after_missing_roles_are_completed(): void
    {
        $this->seed();
        $admin = User::where('authority_level', 'master_admin')->firstOrFail();
        Role::where('slug', 'warehouse')->firstOrFail()->delete();
        $this->actingAs($admin)->get(route('roles.index'))->assertOk()->assertSee('Lengkapi role bawaan');
        $this->post(route('roles.apply-templates'))->assertRedirect();
        $this->get(route('roles.index'))->assertOk()->assertDontSee('Lengkapi role bawaan')->assertSee('Role baru');
    }

    public function test_company_bootstrap_installs_roles_with_only_the_requested_admin(): void
    {
        $method = new \ReflectionMethod(\App\Http\Controllers\TenantController::class, 'bootstrapAdmin');
        $method->invoke(app(\App\Http\Controllers\TenantController::class), [
            'admin_name' => 'Company Admin', 'admin_email' => 'company@example.test', 'admin_password' => 'test-password-123',
        ]);
        $this->assertSame(9, Role::count());
        $this->assertSame(1, User::count());
        $this->assertSame('Company Admin', User::firstOrFail()->name);
        $this->assertTrue(User::firstOrFail()->hasRole('master_admin'));
    }
}
