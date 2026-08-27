<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_role_inherits_parent_permissions(): void
    {
        $permission = Permission::create([
            'module' => 'customers',
            'action' => 'view',
            'key' => 'customers.view',
            'label' => 'View Customers',
        ]);
        $sales = Role::create(['name' => 'Sales', 'slug' => 'sales']);
        $sales->permissions()->attach($permission);
        $supervisor = Role::create([
            'name' => 'Sales Supervisor',
            'slug' => 'sales-supervisor',
            'parent_role_id' => $sales->id,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($supervisor);

        $this->assertTrue($user->canAccess('customers.view'));
        $this->assertCount(1, $supervisor->effectivePermissions());
    }

    public function test_role_hierarchy_cannot_form_a_cycle(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $sales = Role::create(['name' => 'Sales', 'slug' => 'sales']);
        $supervisor = Role::create(['name' => 'Supervisor', 'slug' => 'supervisor', 'parent_role_id' => $sales->id]);

        $this->actingAs($admin)->put(route('roles.update', $sales), [
            'name' => 'Sales',
            'parent_role_id' => $supervisor->id,
        ])->assertStatus(422);
    }

    public function test_seeded_sales_hierarchy_places_csa_above_sales(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $sales = Role::where('slug', 'sales')->firstOrFail();
        $csa = Role::where('slug', 'csa')->firstOrFail();
        $supervisor = Role::where('slug', 'sales_supervisor')->firstOrFail();
        $manager = Role::where('slug', 'sales_manager')->firstOrFail();

        $this->assertSame($sales->id, $csa->parent_role_id);
        $this->assertSame($csa->id, $supervisor->parent_role_id);
        $this->assertSame($supervisor->id, $manager->parent_role_id);
    }

    public function test_master_admin_is_represented_by_a_system_role(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $admin = User::where('authority_level', 'master_admin')->firstOrFail();
        $role = Role::where('slug', 'master_admin')->firstOrFail();

        $this->assertTrue($role->is_system);
        $this->assertTrue($admin->roles->contains($role));
        $this->assertSame(Permission::count(), $role->effectivePermissions()->count());
    }

    public function test_permission_from_template_can_be_disabled_for_new_role(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $permission = Permission::create(['module' => 'customers', 'action' => 'view', 'key' => 'customers.view', 'label' => 'View Customers']);
        $template = Role::create(['name' => 'Template Sales', 'slug' => 'template-sales']);
        $template->permissions()->attach($permission);

        $this->actingAs($admin)->post(route('roles.store'), [
            'name' => 'Sales Terbatas',
            'parent_role_id' => $template->id,
            'permission_ids' => [],
        ])->assertRedirect(route('roles.index'));

        $role = Role::where('slug', 'sales-terbatas')->firstOrFail();
        $this->assertFalse($role->grants('customers.view'));
        $this->assertTrue($role->deniedPermissions->contains($permission));
    }

    public function test_any_role_except_master_admin_can_be_deleted(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $masterRole = Role::where('slug', 'master_admin')->firstOrFail();
        $salesRole = Role::create(['name' => 'Sales', 'slug' => 'sales', 'is_system' => true]);

        $this->actingAs($admin)->delete(route('roles.destroy', $masterRole))->assertStatus(422);
        $this->assertDatabaseHas('roles', ['id' => $masterRole->id]);

        $this->actingAs($admin)->delete(route('roles.destroy', $salesRole))->assertRedirect(route('roles.index'));
        $this->assertDatabaseMissing('roles', ['id' => $salesRole->id]);
    }

    public function test_existing_master_admin_can_create_another_master_admin_account(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $masterRole = Role::where('slug', 'master_admin')->firstOrFail();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Admin Kedua',
            'email' => 'admin.kedua@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'backliner',
            'is_active' => 1,
            'role_ids' => [$masterRole->id],
        ])->assertRedirect(route('users.index'));

        $secondAdmin = User::where('email', 'admin.kedua@example.test')->firstOrFail();
        $this->assertSame('master_admin', $secondAdmin->authority_level);
        $this->assertTrue($secondAdmin->roles->contains($masterRole));
    }

    public function test_user_authority_level_is_derived_from_selected_role(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $managerRole = Role::create([
            'name' => 'Sales Manager',
            'slug' => 'sales_manager',
        ]);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Manager Otomatis',
            'email' => 'manager.otomatis@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'authority_level' => 'staff',
            'user_type' => 'frontliner',
            'is_active' => 1,
            'role_ids' => [$managerRole->id],
        ])->assertRedirect(route('users.index'));

        $manager = User::where('email', 'manager.otomatis@example.test')->firstOrFail();
        $this->assertSame('manager', $manager->authority_level);
        $this->assertTrue($manager->roles->contains($managerRole));
    }

    public function test_approval_right_can_be_overridden_per_account(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $managerRole = Role::create(['name' => 'Sales Manager', 'slug' => 'sales_manager']);
        $financeRole = Role::create(['name' => 'Finance', 'slug' => 'finance']);
        $base = [
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => 1,
        ];

        $this->actingAs($admin)->post(route('users.store'), $base + [
            'name' => 'Manager Tanpa Approval',
            'email' => 'manager.noapproval@example.test',
            'user_type' => 'frontliner',
            'is_approver' => 0,
            'role_ids' => [$managerRole->id],
        ])->assertRedirect(route('users.index'));

        $this->actingAs($admin)->post(route('users.store'), $base + [
            'name' => 'Finance Approver',
            'email' => 'finance.approver@example.test',
            'user_type' => 'backliner',
            'is_approver' => 1,
            'role_ids' => [$financeRole->id],
        ])->assertRedirect(route('users.index'));

        $this->assertFalse(User::where('email', 'manager.noapproval@example.test')->firstOrFail()->canApprove());
        $financeApprover = User::where('email', 'finance.approver@example.test')->firstOrFail();
        $this->assertTrue($financeApprover->canApprove());
        $this->assertTrue($financeApprover->canAccess('approvals.view'));
        $this->actingAs($financeApprover)->get(route('approvals.index'))->assertOk();
    }

    public function test_sales_user_requires_frontliner_type_and_matching_coordinator(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $salesRole = Role::create(['name' => 'Sales', 'slug' => 'sales']);
        $csaRole = Role::create(['name' => 'CSA', 'slug' => 'csa']);
        $financeRole = Role::create(['name' => 'Finance', 'slug' => 'finance']);
        $csa = User::factory()->create(['authority_level' => 'supervisor']);
        $csa->roles()->attach($csaRole);
        $finance = User::factory()->create();
        $finance->roles()->attach($financeRole);
        $payload = [
            'name' => 'Sales Baru',
            'email' => 'sales.baru@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => 1,
            'role_ids' => [$salesRole->id],
        ];

        $this->actingAs($admin)->post(route('users.store'), $payload + [
            'user_type' => 'backliner',
            'manager_id' => $finance->id,
        ])->assertSessionHasErrors('manager_id')
            ->assertSessionDoesntHaveErrors('user_type');

        $this->actingAs($admin)->post(route('users.store'), $payload + [
            'user_type' => 'backliner',
            'manager_id' => $csa->id,
        ])->assertRedirect(route('users.index'));

        $sales = User::where('email', 'sales.baru@example.test')->firstOrFail();
        $this->assertSame($csa->id, $sales->manager_id);
        $this->assertSame('backliner', $sales->user_type);
    }

    public function test_user_cannot_create_circular_coordinator_hierarchy(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $managerRole = Role::create(['name' => 'Sales Manager', 'slug' => 'sales_manager']);
        $manager = User::factory()->create(['authority_level' => 'manager', 'user_type' => 'frontliner']);
        $subordinate = User::factory()->create(['manager_id' => $manager->id]);
        $manager->roles()->attach($managerRole);

        $this->actingAs($admin)->put(route('users.update', $manager), [
            'name' => $manager->name,
            'email' => $manager->email,
            'user_type' => 'frontliner',
            'manager_id' => $subordinate->id,
            'is_active' => 1,
            'role_ids' => [$managerRole->id],
        ])->assertSessionHasErrors('manager_id');

        $this->assertNull($manager->fresh()->manager_id);
    }
}
