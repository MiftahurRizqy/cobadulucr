<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_send_own_presence(): void
    {
        $sales = User::factory()->create(['authority_level' => 'staff', 'is_active' => true]);

        $this->actingAs($sales)->postJson(route('presence.heartbeat'), [
            'path' => '/activities/create',
            'page' => 'Catat Aktivitas · Unified CRM',
        ])->assertNoContent();

        $this->assertDatabaseHas('user_presences', [
            'user_id' => $sales->id,
            'current_page' => 'Mencatat aktivitas',
        ]);
    }

    public function test_sales_cannot_open_active_user_monitor(): void
    {
        $sales = User::factory()->create(['authority_level' => 'staff', 'is_active' => true]);
        $this->actingAs($sales)->get(route('users.active'))->assertForbidden();
    }

    public function test_management_can_open_active_user_monitor(): void
    {
        $manager = User::factory()->create(['authority_level' => 'manager', 'is_active' => true]);
        $this->actingAs($manager)->get(route('users.active'))->assertOk();
    }
}
