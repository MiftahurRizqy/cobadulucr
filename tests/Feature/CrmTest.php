<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\CrmNotification;
use App\Models\Customer;
use App\Models\CustomerRoom;
use App\Models\Department;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Product;
use App\Models\Role;
use App\Models\RoomMember;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrmTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Unified CRM');
    }

    public function test_opportunity_kanban_and_detail_views_render_without_blade_errors(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('authority_level', 'master_admin')->firstOrFail();
        $opportunity = Opportunity::query()->firstOrFail();
        $opportunity->update(['priority' => 'low', 'stage_entered_at' => now()->subDay()]);
        $newestOpportunity = $opportunity->replicate(['opportunity_id']);
        $newestOpportunity->title = 'Opportunity terbaru pada stage';
        $newestOpportunity->stage_entered_at = now();
        $newestOpportunity->save();
        $highPriorityOpportunity = $opportunity->replicate(['opportunity_id']);
        $highPriorityOpportunity->title = 'Opportunity high lebih lama';
        $highPriorityOpportunity->priority = 'high';
        $highPriorityOpportunity->stage_entered_at = now()->subDays(3);
        $highPriorityOpportunity->save();
        $lostStage = $opportunity->pipeline->stages()->where('is_lost', true)->firstOrFail();
        $recentLost = $opportunity->replicate(['opportunity_id']);
        $recentLost->title = 'Closed Lost terbaru masih di Kanban';
        $recentLost->pipeline_stage_id = $lostStage->id;
        $recentLost->status = 'lost';
        $recentLost->stage_entered_at = now()->subDays(5);
        $recentLost->save();
        $archivedLost = $opportunity->replicate(['opportunity_id']);
        $archivedLost->title = 'Closed Lost lama hanya di tabel';
        $archivedLost->pipeline_stage_id = $lostStage->id;
        $archivedLost->status = 'lost';
        $archivedLost->stage_entered_at = now()->subDays(31);
        $archivedLost->save();
        $opportunity->items()->create([
            'product_name' => 'Produk tambahan untuk card',
            'quantity' => 10,
            'quantity_unit' => 'pcs',
            'target_price' => 1000,
            'unit_price' => 1000,
            'subtotal' => 10000,
        ]);

        $this->actingAs($admin)->get(route('opportunities.kanban'))
            ->assertOk()
            ->assertSeeText('Tandai sebagai Lost')
            ->assertSeeText('+1 produk lainnya')
            ->assertSeeText($recentLost->title)
            ->assertSeeText($archivedLost->title)
            ->assertSeeTextInOrder([$newestOpportunity->title, $opportunity->title, $highPriorityOpportunity->title]);
        $this->actingAs($admin)->get(route('opportunities.index', ['pipeline' => $opportunity->pipeline_id, 'status' => 'lost']))
            ->assertOk()
            ->assertSeeText($recentLost->title)
            ->assertSeeText($archivedLost->title);
        $this->actingAs($admin)->get(route('opportunities.show', $opportunity))->assertOk()->assertSeeText('Tandai sebagai Lost');
        $this->actingAs($admin)->get(route('opportunities.index'))->assertOk()->assertSeeText('Filter opportunity');
    }

    public function test_closed_lost_requires_and_stores_a_structured_reason(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('authority_level', 'master_admin')->firstOrFail();
        $opportunity = Opportunity::query()->where('status', 'open')->firstOrFail();
        $lostStage = $opportunity->pipeline->stages()->where('is_lost', true)->firstOrFail();

        $this->actingAs($admin)->post(route('opportunities.stage', $opportunity), [
            'stage_id' => $lostStage->id,
        ])->assertSessionHasErrors(['lost_reason', 'reason']);

        $this->assertNotSame($lostStage->id, $opportunity->fresh()->pipeline_stage_id);

        $this->actingAs($admin)->post(route('opportunities.stage', $opportunity), [
            'stage_id' => $lostStage->id,
            'lost_reason' => 'competitor',
            'reason' => 'Customer memilih penawaran kompetitor dengan waktu kirim lebih cepat.',
        ])->assertRedirect();

        $opportunity->refresh();
        $this->assertSame('lost', $opportunity->status);
        $this->assertSame($lostStage->id, $opportunity->pipeline_stage_id);
        $this->assertSame('competitor', $opportunity->lost_reason);
        $this->assertSame('Customer memilih penawaran kompetitor dengan waktu kirim lebih cepat.', $opportunity->lost_reason_detail);
        $this->assertDatabaseHas('opportunity_stage_histories', [
            'opportunity_id' => $opportunity->id,
            'to_stage_id' => $lostStage->id,
            'reason' => 'Customer memilih penawaran kompetitor dengan waktu kirim lebih cepat.',
        ]);
        $this->actingAs($admin)->get(route('opportunities.show', $opportunity))
            ->assertOk()
            ->assertSeeText('Kompetitor')
            ->assertSeeText('Customer memilih penawaran kompetitor dengan waktu kirim lebih cepat.');
    }

    public function test_ai_generated_evidence_marker_is_flagged_for_manual_review(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('chatgpt-generated-image.png', 1024, 1024);
        $path = $file->store('crm/activities', 'public');
        $attachment = Attachment::create([
            'attachable_type' => Activity::class,
            'attachable_id' => 999,
            'uploaded_by' => $user->id,
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => 'image/png',
            'size' => $file->getSize(),
            'verification_status' => 'unavailable',
            'evidence_metadata' => ['capture_source' => 'upload'],
        ]);

        $result = app(\App\Services\AiEvidenceDetector::class)->analyze($attachment);

        $this->assertSame('suspected', $result['level']);
        $this->assertSame('ai_suspected', $attachment->fresh()->verification_status);

        $attachment->update(['verification_status' => 'duplicate']);
        app(\App\Services\AiEvidenceDetector::class)->analyze($attachment->fresh());
        $this->assertSame('duplicate', $attachment->fresh()->verification_status);
        $this->assertSame('suspected', data_get($attachment->fresh()->evidence_metadata, 'ai_detection.level'));
    }

    public function test_sales_cannot_see_evidence_fraud_flags_but_admin_can(): void
    {
        $permission = Permission::create(['module' => 'activities', 'action' => 'view', 'key' => 'activities.view', 'label' => 'View activities']);
        $role = Role::create(['name' => 'Sales', 'slug' => 'sales']);
        $role->permissions()->attach($permission);
        $sales = User::factory()->create(['user_type' => 'frontliner', 'authority_level' => 'staff']);
        $sales->roles()->attach($role);
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $customer = $this->customer(['sales_owner_id' => $sales->id, 'created_by' => $sales->id]);
        $activity = Activity::create([
            'customer_id' => $customer->id,
            'user_id' => $sales->id,
            'type' => 'visit',
            'summary' => 'Visit dengan bukti rahasia',
            'occurred_at' => now(),
            'status' => 'completed',
        ]);
        Attachment::create([
            'attachable_type' => Activity::class,
            'attachable_id' => $activity->id,
            'uploaded_by' => $sales->id,
            'name' => 'chatgpt-image.png',
            'path' => 'crm/activities/ai.webp',
            'mime_type' => 'image/webp',
            'size' => 100,
            'verification_status' => 'ai_suspected',
            'evidence_metadata' => ['ai_detection' => ['level' => 'suspected', 'reasons' => ['Penanda generator ditemukan: chatgpt']]],
            'verification_notes' => ['Gambar terindikasi AI.'],
        ]);

        $this->actingAs($sales)->get(route('activities.index', ['activity' => $activity->id]))
            ->assertOk()
            ->assertDontSeeText('Terindikasi AI')
            ->assertDontSeeText('Terindikasi gambar AI')
            ->assertDontSeeText('Perlu dicek')
            ->assertDontSeeText('Penanda generator ditemukan');

        $this->actingAs($admin)->get(route('activities.index', ['activity' => $activity->id]))
            ->assertOk()
            ->assertSeeText('Terindikasi AI')
            ->assertSeeText('Terindikasi gambar AI')
            ->assertSeeText('Perlu dicek');
    }

    public function test_master_admin_can_login_and_open_main_modules(): void
    {
        $admin = User::factory()->create(['email' => 'admin@test.local', 'password' => 'password', 'authority_level' => 'master_admin', 'is_active' => true]);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/');
        foreach (['/', '/leads', '/customers', '/opportunities', '/tasks', '/approvals', '/users', '/areas', '/roles', '/pipelines', '/settings/activity-evidence', '/audit-log'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@test.local',
            'password' => 'password',
            'is_active' => false,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors([
            'email' => 'Akun sedang dinonaktifkan.',
        ]);

        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_logged_in_user_is_logged_out_after_account_becomes_inactive(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->get('/')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'Akun Anda sedang dinonaktifkan.',
            ]);

        $this->assertGuest();
    }

    public function test_frontliner_only_sees_owned_or_assigned_customers(): void
    {
        $permission = Permission::create(['module' => 'customers', 'action' => 'view', 'key' => 'customers.view', 'label' => 'View customers']);
        $role = Role::create(['name' => 'Sales', 'slug' => 'sales']);
        $role->permissions()->attach($permission);
        $salesA = User::factory()->create(['user_type' => 'frontliner', 'authority_level' => 'staff']);
        $salesB = User::factory()->create(['user_type' => 'frontliner', 'authority_level' => 'staff']);
        $salesA->roles()->attach($role);

        $own = $this->customer(['company_name' => 'Customer Milik A', 'sales_owner_id' => $salesA->id, 'created_by' => $salesA->id]);
        $other = $this->customer(['company_name' => 'Customer Milik B', 'sales_owner_id' => $salesB->id, 'created_by' => $salesB->id]);

        $this->actingAs($salesA)->get('/customers')->assertOk()->assertSeeText($own->company_name)->assertDontSeeText($other->company_name);
        $this->actingAs($salesA)->get(route('customers.show', $other))->assertForbidden();
    }

    public function test_sales_activity_list_is_own_only_and_admin_can_filter_by_account(): void
    {
        $permission = Permission::create(['module' => 'activities', 'action' => 'view', 'key' => 'activities.view', 'label' => 'View activities']);
        $role = Role::create(['name' => 'Sales Activity', 'slug' => 'sales']);
        $role->permissions()->attach($permission);
        $salesA = User::factory()->create(['name' => 'Sales Alpha', 'user_type' => 'frontliner', 'authority_level' => 'staff']);
        $salesB = User::factory()->create(['name' => 'Sales Beta', 'user_type' => 'frontliner', 'authority_level' => 'staff']);
        $salesA->roles()->attach($role);
        $salesB->roles()->attach($role);
        $customer = $this->customer(['sales_owner_id' => $salesA->id, 'created_by' => $salesA->id]);
        $customer->assignedUsers()->attach($salesB->id, ['responsibility' => 'sales']);

        Activity::create(['customer_id' => $customer->id, 'user_id' => $salesA->id, 'type' => 'visit', 'summary' => 'Aktivitas rahasia Alpha', 'result' => 'Hasil Alpha', 'occurred_at' => now()]);
        Activity::create(['customer_id' => $customer->id, 'user_id' => $salesB->id, 'type' => 'call', 'summary' => 'Aktivitas rahasia Beta', 'result' => 'Hasil Beta', 'occurred_at' => now()]);
        Activity::create(['customer_id' => $customer->id, 'user_id' => $salesA->id, 'type' => 'meeting', 'summary' => 'Meeting Alpha mendatang', 'occurred_at' => now()->addDay()]);
        Activity::create(['customer_id' => $customer->id, 'user_id' => $salesA->id, 'type' => 'email', 'summary' => 'Aktivitas Alpha tahun lalu', 'occurred_at' => now()->subYear()]);

        $this->actingAs($salesA)->get(route('activities.index'))
            ->assertOk()->assertDontSeeText('Semua status')->assertSeeText('Aktivitas rahasia Alpha')->assertSeeText('Meeting Alpha mendatang')->assertDontSeeText('Aktivitas rahasia Beta');
        $this->actingAs($salesA)->get(route('activities.index', ['period' => 'today']))
            ->assertOk()->assertSeeText('Aktivitas rahasia Alpha')->assertDontSeeText('Meeting Alpha mendatang')->assertDontSeeText('Aktivitas Alpha tahun lalu');
        $this->actingAs($salesA)->get(route('activities.index', [
            'period' => 'custom',
            'date_from' => now()->subYear()->startOfDay()->toDateString(),
            'date_to' => now()->subYear()->endOfDay()->toDateString(),
        ]))->assertOk()->assertSeeText('Aktivitas Alpha tahun lalu')->assertDontSeeText('Aktivitas rahasia Alpha');
        $this->actingAs($salesA)->get(route('activities.index', ['user_id' => $salesB->id]))
            ->assertOk()->assertSeeText('Aktivitas rahasia Alpha')->assertDontSeeText('Aktivitas rahasia Beta');

        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $this->actingAs($admin)->get(route('activities.index', ['user_id' => $salesB->id]))
            ->assertOk()->assertSeeText('Aktivitas rahasia Beta')->assertDontSeeText('Aktivitas rahasia Alpha');
    }

    public function test_activity_collaborator_receives_access_notification_and_can_comment(): void
    {
        $permission = Permission::create(['module' => 'activities', 'action' => 'view', 'key' => 'activities.view', 'label' => 'View activities']);
        $role = Role::create(['name' => 'Sales Collaboration', 'slug' => 'sales']);
        $role->permissions()->attach($permission);
        $owner = User::factory()->create(['authority_level' => 'master_admin', 'name' => 'Pembuat Aktivitas']);
        $collaborator = User::factory()->create(['authority_level' => 'staff', 'name' => 'Rekan Penting']);
        $collaborator->roles()->attach($role);
        $customer = $this->customer(['sales_owner_id' => $owner->id, 'created_by' => $owner->id]);

        $this->actingAs($owner)->post(route('activities.store'), [
            'customer_id' => $customer->id,
            'type' => 'meeting',
            'summary' => 'Meeting kolaborasi penting',
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'participant_ids' => [$collaborator->id],
        ])->assertRedirect();

        $activity = Activity::where('summary', 'Meeting kolaborasi penting')->firstOrFail();
        $this->assertContains($collaborator->id, $activity->participants);
        $this->assertDatabaseHas('notifications', ['user_id' => $collaborator->id, 'type' => 'activity_invitation']);
        $this->actingAs($collaborator)->get(route('activities.index'))
            ->assertOk()
            ->assertSeeText('Meeting kolaborasi penting');

        $this->actingAs($collaborator)->postJson(route('activities.comments.store', $activity), [
            'body' => 'Saya siap membantu aktivitas ini.',
        ])->assertCreated()->assertJsonPath('message.body', 'Saya siap membantu aktivitas ini.');
        $this->assertDatabaseHas('comments', [
            'commentable_type' => Activity::class,
            'commentable_id' => $activity->id,
            'user_id' => $collaborator->id,
        ]);
        $this->assertDatabaseHas('notifications', ['user_id' => $owner->id, 'type' => 'activity_comment']);

        $lateCollaborator = User::factory()->create(['authority_level' => 'staff', 'name' => 'Rekan Menyusul']);
        $lateCollaborator->roles()->attach($role);
        $this->actingAs($owner)->post(route('activities.participants.store', $activity), [
            'participant_ids' => [$lateCollaborator->id],
        ])->assertRedirect(route('activities.index', ['activity' => $activity->id]));

        $this->assertContains($lateCollaborator->id, $activity->fresh()->participants);
        $this->assertDatabaseHas('notifications', ['user_id' => $lateCollaborator->id, 'type' => 'activity_invitation']);
        $this->actingAs($lateCollaborator)->get(route('activities.index', ['activity' => $activity->id]))
            ->assertOk()
            ->assertSeeText('Meeting kolaborasi penting');
    }

    public function test_approval_activity_requires_and_stores_its_specific_details(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $manager = User::factory()->create(['authority_level' => 'manager', 'is_approver' => true]);
        $customer = $this->customer(['sales_owner_id' => $admin->id, 'created_by' => $admin->id]);
        $pipeline = Pipeline::create(['name' => 'Special Price', 'slug' => 'special-price', 'created_by' => $admin->id]);
        $stage = $pipeline->stages()->create(['name' => 'Quotation', 'slug' => 'quotation', 'position' => 1, 'probability' => 65]);
        $opportunity = Opportunity::create(['customer_id' => $customer->id, 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'owner_id' => $admin->id, 'title' => 'Harga Khusus Multi Produk', 'probability' => 65]);
        $item = $opportunity->items()->create(['product_name' => 'Cup 12 oz', 'quantity' => 10000, 'quantity_unit' => 'pcs', 'target_price' => 1300, 'unit_price' => 1500, 'subtotal' => 15000000]);
        $basePayload = [
            'customer_id' => $customer->id,
            'opportunity_id' => $opportunity->id,
            'type' => 'approval_special_price',
            'summary' => 'Pengajuan harga khusus produk A',
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'status' => 'completed',
        ];

        $this->actingAs($admin)->post(route('activities.store'), $basePayload)
            ->assertSessionHasErrors([
                'special_price_items',
                'participant_ids',
            ]);

        $this->actingAs($admin)->post(route('activities.store'), $basePayload + [
            'participant_ids' => [$manager->id],
            'special_price_items' => [
                $item->id => [
                    'selected' => 1,
                    'requested_price' => 1250,
                    'reason' => 'Customer berkomitmen melakukan pembelian rutin.',
                ],
            ],
        ])->assertRedirect(route('customers.show', $customer));

        $activity = Activity::where('summary', 'Pengajuan harga khusus produk A')->firstOrFail();
        $this->assertSame('Cup 12 oz', $activity->approvalDetail->product_name);
        $this->assertSame('1250.00', $activity->approvalDetail->requested_price);
        $this->assertSame($item->id, $activity->approvalDetail->special_price_items[0]['opportunity_item_id']);
        $this->assertDatabaseHas('activity_approval_details', [
            'activity_id' => $activity->id,
            'product_name' => 'Cup 12 oz',
            'requested_price' => 1250,
        ]);

        $captcha = '12345';
        $token = Crypt::encryptString(json_encode([
            'activity_id' => $activity->id,
            'code' => $captcha,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]));
        $this->actingAs($manager)->from(route('approvals.index'))->post(route('activities.approval.decision', $activity), [
            'decision' => 'approved',
            'captcha_answer' => $captcha,
            'captcha_token' => $token,
            'item_decisions' => [
                $item->id => ['decision' => 'approved', 'note' => ''],
            ],
        ])->assertSessionHasErrors('item_decisions.'.$item->id.'.note');
        $this->assertSame('pending', $activity->approvalDetail->fresh()->approval_status);

        $this->actingAs($manager)->post(route('activities.approval.decision', $activity), [
            'decision' => 'approved',
            'captcha_answer' => $captcha,
            'captcha_token' => $token,
            'item_decisions' => [
                $item->id => ['decision' => 'approved', 'note' => 'Margin masih sesuai.'],
            ],
        ])->assertRedirect();
        $this->assertSame('1250.00', $item->fresh()->unit_price);
        $this->assertSame('approved', $activity->approvalDetail->fresh()->special_price_items[0]['status']);

        $this->actingAs($admin)->get(route('approvals.index', ['activity' => $activity->id]).'#approval-'.$activity->id)
            ->assertOk()
            ->assertSeeText('Pengajuan harga khusus produk A')
            ->assertSeeText('Opportunity terkait')
            ->assertSeeText($opportunity->opportunity_id)
            ->assertSeeText('Harga Khusus Multi Produk')
            ->assertSee(route('opportunities.show', $opportunity), false)
            ->assertSeeText('Approved')
            ->assertSeeText('Decision')
            ->assertSeeText('Approved by')
            ->assertSeeText($manager->name)
            ->assertSeeText('Margin masih sesuai.');
    }

    public function test_approval_requester_cannot_approve_their_own_request_even_as_master_admin(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $manager = User::factory()->create(['authority_level' => 'manager', 'is_approver' => true]);
        $customer = $this->customer(['sales_owner_id' => $admin->id, 'created_by' => $admin->id]);
        $activity = Activity::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'type' => 'approval_budget',
            'summary' => 'Anggaran buatan admin',
            'occurred_at' => now(),
            'participants' => [$manager->id],
            'status' => 'completed',
        ]);
        $activity->approvalDetail()->create([
            'need_name' => 'Event customer',
            'budget_amount' => 5000000,
            'needed_at' => now()->addWeek()->toDateString(),
            'reason' => 'Mendukung event customer.',
        ]);

        $this->actingAs($admin)->post(route('activities.approval.decision', $activity), [
            'decision' => 'approved',
        ])->assertForbidden();
        $this->assertSame('pending', $activity->approvalDetail->fresh()->approval_status);
    }

    public function test_rejecting_approval_requires_a_note_but_not_account_password(): void
    {
        $activityPermission = Permission::create(['module' => 'activities', 'action' => 'view', 'key' => 'activities.view', 'label' => 'View activities']);
        $approvalPermission = Permission::create(['module' => 'approvals', 'action' => 'view', 'key' => 'approvals.view', 'label' => 'View approvals']);
        $managerRole = Role::create(['name' => 'Manager Pemutus', 'slug' => 'sales_manager']);
        $managerRole->permissions()->attach([$activityPermission->id, $approvalPermission->id]);
        $requester = User::factory()->create();
        $manager = User::factory()->create(['authority_level' => 'manager', 'is_approver' => true]);
        $manager->roles()->attach($managerRole);
        $customer = $this->customer(['sales_owner_id' => $requester->id, 'created_by' => $requester->id]);
        $activity = Activity::create([
            'customer_id' => $customer->id,
            'user_id' => $requester->id,
            'type' => 'approval_budget',
            'summary' => 'Pengajuan anggaran untuk ditolak',
            'occurred_at' => now(),
            'participants' => [$manager->id],
        ]);
        $activity->approvalDetail()->create([
            'need_name' => 'Program customer',
            'budget_amount' => 5000000,
            'needed_at' => now()->addWeek()->toDateString(),
            'reason' => 'Pengajuan anggaran program.',
        ]);

        $this->actingAs($manager)->post(route('activities.approval.decision', $activity), [
            'decision' => 'rejected',
            'decision_note' => 'Anggaran belum tersedia.',
        ])->assertRedirect();

        $this->assertSame('rejected', $activity->approvalDetail->fresh()->approval_status);
    }

    public function test_payment_term_approval_calculates_additional_days(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $manager = User::factory()->create(['authority_level' => 'manager', 'is_approver' => true]);
        $customer = $this->customer(['sales_owner_id' => $admin->id, 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('activities.store'), [
            'customer_id' => $customer->id,
            'type' => 'approval_payment_term',
            'summary' => 'Pengajuan tempo 30 hari',
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'status' => 'completed',
            'participant_ids' => [$manager->id],
            'approval_details' => [
                'transaction_value' => 70600000,
                'current_days' => 14,
                'requested_days' => 30,
                'reason' => 'Customer membutuhkan penyesuaian arus kas.',
            ],
        ])->assertRedirect();

        $activity = Activity::where('summary', 'Pengajuan tempo 30 hari')->firstOrFail();
        $this->assertSame(16, $activity->approvalDetail->additional_days);
        $this->assertSame('70600000.00', $activity->approvalDetail->transaction_value);
    }

    public function test_activity_create_form_renders_all_approval_schemas(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        User::factory()->create(['name' => 'Approver Terlihat', 'is_active' => true, 'is_approver' => true]);

        $this->actingAs($admin)->get(route('activities.create'))
            ->assertOk()
            ->assertDontSeeText('Detail pengajuan Kirim Sampel')
            ->assertSeeText('Kirim Sampel')
            ->assertSeeText('Approver Terlihat')
            ->assertSeeText('Detail pengajuan Batas Kredit')
            ->assertSeeText('Detail pengajuan Tempo Pembayaran');
    }

    public function test_manager_is_notified_and_can_decide_activity_approval(): void
    {
        $permission = Permission::create(['module' => 'activities', 'action' => 'view', 'key' => 'activities.view', 'label' => 'View activities']);
        $approvalPermission = Permission::create(['module' => 'approvals', 'action' => 'view', 'key' => 'approvals.view', 'label' => 'View approvals']);
        $salesRole = Role::create(['name' => 'Sales Approval', 'slug' => 'sales']);
        $managerRole = Role::create(['name' => 'Manager Approval', 'slug' => 'sales_manager']);
        $salesRole->permissions()->attach([$permission->id, $approvalPermission->id]);
        $managerRole->permissions()->attach([$permission->id, $approvalPermission->id]);
        $manager = User::factory()->create(['name' => 'Manager Pemutus', 'authority_level' => 'manager', 'is_approver' => true]);
        $manager->roles()->attach($managerRole);
        $sales = User::factory()->create(['name' => 'Sales Pengaju', 'authority_level' => 'staff', 'manager_id' => $manager->id]);
        $sales->roles()->attach($salesRole);
        $customer = $this->customer([
            'sales_owner_id' => $sales->id,
            'created_by' => $sales->id,
            'credit_limit' => 10000000,
        ]);

        $this->actingAs($sales)->post(route('activities.store'), [
            'customer_id' => $customer->id,
            'type' => 'approval_credit_limit',
            'summary' => 'Pengajuan kenaikan batas kredit',
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'status' => 'completed',
            'participant_ids' => [$manager->id],
            'approval_details' => [
                'po_number' => 'PO-2026-001',
                'new_order_value' => 70000000,
                'requested_limit' => 25000000,
                'outstanding_receivables' => 22500000,
                'planned_payment_date' => now()->addWeek()->toDateString(),
                'planned_payment_amount' => 10000000,
                'reason' => 'Volume transaksi meningkat.',
            ],
        ])->assertRedirect();

        $activity = Activity::where('summary', 'Pengajuan kenaikan batas kredit')->firstOrFail();
        $this->assertContains($manager->id, $activity->participants);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'type' => 'activity_approval_waiting',
        ]);
        $this->actingAs($manager)->get(route('approvals.index', ['activity' => $activity->id]))
            ->assertOk()
            ->assertSeeText('Pengajuan kenaikan batas kredit')
            ->assertSeeText('Approve')
            ->assertSeeText('Reject')
            ->assertSeeText('Request Revision');
        $captchaResponse = $this->actingAs($manager)->getJson(route('approvals.captcha', $activity));
        $captchaResponse->assertOk()->assertJsonStructure(['image', 'token']);
        $this->assertStringStartsWith('data:image/png;base64,', $captchaResponse->json('image'));

        $this->actingAs($manager)->post(route('activities.approval.decision', $activity), [
            'decision' => 'revision',
            'decision_note' => 'Perjelas alasan dan turunkan batas kredit yang diajukan.',
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_approval_details', [
            'activity_id' => $activity->id,
            'approval_status' => 'revision',
            'decided_by' => $manager->id,
        ]);
        $this->assertSame('10000000.00', $customer->fresh()->credit_limit);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $sales->id,
            'type' => 'activity_approval_decided',
            'url' => route('approvals.revise', $activity, false),
        ]);

        $this->actingAs($sales)->get(route('approvals.revise', $activity))
            ->assertOk()
            ->assertSeeText('Perjelas alasan dan turunkan batas kredit yang diajukan.');
        $this->actingAs($sales)->patch(route('approvals.resubmit', $activity), [
            'summary' => 'Pengajuan kenaikan batas kredit diperbaiki',
            'approval_details' => [
                'po_number' => 'PO-2026-001',
                'new_order_value' => 70000000,
                'requested_limit' => 30000000,
                'outstanding_receivables' => 20000000,
                'planned_payment_date' => now()->addWeek()->toDateString(),
                'planned_payment_amount' => 10000000,
                'reason' => 'Volume meningkat dan pembayaran enam bulan terakhir selalu tepat waktu.',
            ],
        ])->assertRedirect(route('approvals.index'))
            ->assertSessionHas('success', 'Perbaikan berhasil diajukan ulang kepada approver.');

        $this->get(route('approvals.index'))
            ->assertOk()
            ->assertSeeText('Perbaikan berhasil diajukan ulang kepada approver.')
            ->assertSee('z-[200]', false);

        $this->actingAs($sales)->get(route('approvals.revise', $activity))
            ->assertRedirect(route('approvals.index', ['status' => 'pending', 'activity' => $activity->id]))
            ->assertSessionHas('success', 'Pengajuan ini sudah diajukan ulang atau telah diputuskan.');

        $activity->refresh();
        $this->assertSame('pending', $activity->approvalDetail->approval_status);
        $this->assertNull($activity->approvalDetail->decision_note);
        $this->assertSame('-10000000.00', $activity->approvalDetail->remaining_limit);
        $this->assertSame('70000000.00', $activity->approvalDetail->over_limit);

        $captchaToken = Crypt::encryptString(json_encode([
            'activity_id' => $activity->id,
            'code' => '48279',
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]));

        $this->actingAs($manager)->from(route('approvals.index'))->post(route('activities.approval.decision', $activity), [
            'decision' => 'approved',
            'decision_note' => 'Perbaikan sudah sesuai.',
            'captcha_answer' => '11111',
            'captcha_token' => $captchaToken,
        ])->assertRedirect(route('approvals.index'))->assertSessionHasErrors('captcha_answer');
        $this->assertSame('pending', $activity->approvalDetail->fresh()->approval_status);

        $this->actingAs($manager)->post(route('activities.approval.decision', $activity), [
            'decision' => 'approved',
            'decision_note' => 'Perbaikan sudah sesuai.',
            'captcha_answer' => '48279',
            'captcha_token' => $captchaToken,
        ])->assertRedirect();
        $this->assertDatabaseHas('activity_approval_details', [
            'activity_id' => $activity->id,
            'approval_status' => 'approved',
            'decided_by' => $manager->id,
        ]);
        $this->assertSame('30000000.00', $customer->fresh()->credit_limit);
    }

    public function test_backliner_only_sees_customer_after_room_invitation(): void
    {
        $permission = Permission::create(['module' => 'customers', 'action' => 'view', 'key' => 'customers.view', 'label' => 'View customers']);
        $role = Role::create(['name' => 'Finance', 'slug' => 'finance']);
        $role->permissions()->attach($permission);
        $sales = User::factory()->create(['user_type' => 'frontliner']);
        $finance = User::factory()->create(['user_type' => 'backliner']);
        $finance->roles()->attach($role);
        $customer = $this->customer(['sales_owner_id' => $sales->id, 'created_by' => $sales->id]);
        $room = CustomerRoom::create(['customer_id' => $customer->id, 'name' => 'Test Room', 'owner_id' => $sales->id]);

        $this->actingAs($finance)->get('/customers')->assertOk()->assertDontSeeText($customer->company_name);
        RoomMember::create(['customer_room_id' => $room->id, 'user_id' => $finance->id, 'access_level' => 'viewer', 'invited_by' => $sales->id]);
        $this->actingAs($finance)->get('/customers')->assertOk()->assertSeeText($customer->company_name);
    }

    public function test_navigation_hides_modules_without_permission_on_every_layout_surface(): void
    {
        $dashboard = Permission::create(['module' => 'dashboard', 'action' => 'view', 'key' => 'dashboard.view', 'label' => 'View dashboard']);
        Permission::create(['module' => 'customers', 'action' => 'view', 'key' => 'customers.view', 'label' => 'View customers']);
        Permission::create(['module' => 'opportunities', 'action' => 'view', 'key' => 'opportunities.view', 'label' => 'View opportunities']);

        $role = Role::create(['name' => 'Limited Sales', 'slug' => 'limited-sales']);
        $role->permissions()->attach($dashboard);
        $sales = User::factory()->create(['user_type' => 'frontliner', 'authority_level' => 'staff']);
        $sales->roles()->attach($role);

        $response = $this->actingAs($sales)->get('/')->assertOk()->assertSeeText('Ringkasan');

        foreach ([
            route('customers.index'),
            route('customers.create'),
            route('opportunities.index'),
            route('opportunities.create'),
            route('opportunities.kanban'),
        ] as $url) {
            $response->assertDontSee('href="'.$url.'"', false);
        }
    }

    public function test_mandatory_stage_rule_blocks_invalid_transition(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $customer = $this->customer(['sales_owner_id' => $admin->id, 'created_by' => $admin->id]);
        $pipeline = Pipeline::create(['name' => 'Test', 'slug' => 'test', 'created_by' => $admin->id]);
        $first = PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => 'New', 'slug' => 'new', 'position' => 1, 'probability' => 10]);
        $quotation = PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Quotation', 'slug' => 'quotation', 'position' => 2, 'probability' => 60]);
        $quotation->rules()->create(['rule_type' => 'field', 'field_key' => 'offered_price', 'label' => 'Harga wajib diisi']);
        $opportunity = Opportunity::create(['customer_id' => $customer->id, 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $first->id, 'owner_id' => $admin->id, 'title' => 'Test Opportunity', 'estimated_value' => 1000000, 'probability' => 10]);

        $this->actingAs($admin)->post(route('opportunities.stage', $opportunity), ['stage_id' => $quotation->id])->assertSessionHasErrors('stage');
        $opportunity->update(['offered_price' => 950000]);
        $this->actingAs($admin)->postJson(route('opportunities.stage', $opportunity), [
            'stage_id' => $quotation->id,
            'reason' => 'Dipindahkan melalui drag & drop Kanban',
        ])->assertOk()->assertJson(['stage_id' => $quotation->id]);
        $this->assertSame($quotation->id, $opportunity->fresh()->pipeline_stage_id);
        $this->assertDatabaseHas('opportunity_stage_histories', [
            'opportunity_id' => $opportunity->id,
            'from_stage_id' => $first->id,
            'to_stage_id' => $quotation->id,
            'changed_by' => $admin->id,
        ]);
        $this->actingAs($admin)->get(route('opportunities.show', $opportunity))
            ->assertOk()
            ->assertSeeText('New → Quotation')
            ->assertSeeText('Oleh '.$admin->name);
    }

    public function test_task_assignment_creates_notification(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $assignee = User::factory()->create(['user_type' => 'backliner']);
        $this->actingAs($admin)->post('/tasks', ['title' => 'Review supplier', 'priority' => 'high', 'assignee_ids' => [$assignee->id]])->assertRedirect('/tasks');
        $this->assertDatabaseHas('task_user', ['user_id' => $assignee->id]);
        $this->assertTrue(CrmNotification::where('user_id', $assignee->id)->where('type', 'task_assigned')->exists());
    }

    public function test_notification_poll_only_returns_the_logged_in_users_latest_notification(): void
    {
        $user = User::factory()->create(['authority_level' => 'master_admin']);
        $other = User::factory()->create(['authority_level' => 'master_admin']);
        CrmNotification::create(['user_id' => $other->id, 'type' => 'test', 'title' => 'Rahasia user lain', 'message' => 'Tidak boleh terlihat']);
        $own = CrmNotification::create(['user_id' => $user->id, 'type' => 'test', 'title' => 'Task baru masuk', 'message' => 'Silakan diperiksa']);

        $this->actingAs($user)->getJson(route('notifications.poll'))
            ->assertOk()
            ->assertJsonPath('latest.id', $own->id)
            ->assertJsonPath('latest.title', 'Task baru masuk')
            ->assertJsonPath('notifications.0.id', $own->id)
            ->assertJsonPath('notifications.0.read', false)
            ->assertJsonPath('unread_count', 1)
            ->assertJsonMissing(['title' => 'Rahasia user lain']);
    }

    public function test_notification_popup_includes_pending_follow_up_items_counted_by_the_badge(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $customer = $this->customer(['sales_owner_id' => $admin->id, 'created_by' => $admin->id]);
        $activity = Activity::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'type' => 'call',
            'summary' => 'Hubungi customer besok',
            'occurred_at' => now(),
            'next_follow_up_at' => now()->addDay(),
            'status' => 'completed',
        ]);

        $this->actingAs($admin)->getJson(route('notifications.poll'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonFragment([
                'id' => 'follow-up-'.$activity->id,
                'title' => 'Follow-up segera',
                'message' => 'Hubungi customer besok · '.$customer->company_name,
            ]);
    }

    public function test_department_policy_requires_activity_image_and_stores_valid_preview_file(): void
    {
        Storage::fake('public');
        $permission = Permission::create(['module' => 'activities', 'action' => 'view', 'key' => 'activities.view', 'label' => 'View activities']);
        $role = Role::create(['name' => 'Sales Bukti', 'slug' => 'sales']);
        $role->permissions()->attach($permission);
        $sales = User::factory()->create(['authority_level' => 'staff']);
        $sales->roles()->attach($role);
        $salesDepartment = Department::create(['code' => 'SLS-TEST', 'name' => 'Sales Test', 'activity_evidence_required' => true]);
        $sales->departments()->attach($salesDepartment);
        $customer = $this->customer(['sales_owner_id' => $sales->id, 'created_by' => $sales->id]);
        $payload = [
            'customer_id' => $customer->id,
            'type' => 'visit',
            'summary' => 'Kunjungan customer',
            'result' => 'Kebutuhan customer berhasil dicatat.',
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'status' => 'completed',
        ];

        $this->actingAs($sales)->post(route('activities.store'), $payload)
            ->assertSessionHasErrors('attachments');
        $this->assertDatabaseMissing('activities', ['summary' => 'Kunjungan customer']);

        $file = UploadedFile::fake()->create('bukti-visit.jpg', 250, 'image/jpeg');
        $this->actingAs($sales)->post(route('activities.store'), $payload + [
            'attachments' => [$file],
            'attachment_metadata' => json_encode([[
                'name' => 'bukti-visit.jpg',
                'size' => 250 * 1024,
                'type' => 'image/jpeg',
                'lastModified' => now()->subMinutes(5)->getTimestampMs(),
                'source' => 'camera',
                'latitude' => -6.2,
                'longitude' => 106.8166667,
                'accuracy' => 12.5,
                'locationRecordedAt' => now()->subMinutes(5)->getTimestampMs(),
            ]]),
        ])
            ->assertRedirect(route('customers.show', $customer));

        $attachment = \App\Models\Attachment::firstOrFail();
        $this->assertSame('bukti-visit.jpg', $attachment->name);
        $this->assertSame(64, strlen($attachment->sha256));
        $this->assertNotNull($attachment->client_modified_at);
        $this->assertSame('device_location', $attachment->verification_status);
        $this->assertSame('camera', $attachment->evidence_metadata['capture_source']);
        $this->assertSame(-6.2, $attachment->evidence_metadata['device_latitude']);
        $this->assertSame(106.8166667, $attachment->evidence_metadata['device_longitude']);
        $this->assertSame(12.5, $attachment->evidence_metadata['device_accuracy']);
        $this->assertTrue($attachment->checksumIsValid());
        Storage::disk('public')->assertExists($attachment->path);

        $managerRole = Role::create(['name' => 'Manager Aktivitas', 'slug' => 'sales_manager']);
        $managerRole->permissions()->attach($permission);
        $manager = User::factory()->create(['authority_level' => 'manager']);
        $manager->roles()->attach($managerRole);
        $manager->departments()->attach($salesDepartment);
        $businessUnit = \App\Models\BusinessUnit::create(['code' => 'BU-MGR-EVIDENCE', 'name' => 'Manager Evidence']);
        $managerCustomer = $this->customer(['sales_owner_id' => $manager->id, 'created_by' => $manager->id, 'business_unit_id' => $businessUnit->id]);
        $manager->businessUnits()->attach($businessUnit);
        $this->actingAs($manager)->post(route('activities.store'), [
            'customer_id' => $managerCustomer->id,
            'type' => 'visit',
            'summary' => 'Kunjungan manager tanpa bukti',
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'status' => 'completed',
        ])->assertRedirect(route('customers.show', $managerCustomer));
    }

    public function test_planned_activity_routes_are_removed(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('activities.execute'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('activities.execute.store'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('activities.cancel'));

        return;

        Storage::fake('public');
        $permission = Permission::create([
            'module' => 'activities',
            'action' => 'view',
            'key' => 'activities.view',
            'label' => 'View activities',
        ]);
        $role = Role::create(['name' => 'Sales Eksekusi', 'slug' => 'sales']);
        $role->permissions()->attach($permission);
        $sales = User::factory()->create(['authority_level' => 'staff']);
        $sales->roles()->attach($role);
        $department = Department::create([
            'code' => 'SLS-EXECUTE',
            'name' => 'Sales Execute',
            'activity_evidence_required' => true,
        ]);
        $sales->departments()->attach($department);
        $customer = $this->customer(['sales_owner_id' => $sales->id, 'created_by' => $sales->id]);

        $planned = Activity::create([
            'customer_id' => $customer->id,
            'user_id' => $sales->id,
            'type' => 'visit',
            'summary' => 'Visit yang akan dikerjakan',
            'occurred_at' => now()->addDay(),
            'status' => 'planned',
        ]);
        $initialActivityCount = Activity::count();

        $this->actingAs($sales)->get(route('activities.execute', $planned))
            ->assertOk()
            ->assertSeeText('Menyelesaikan aktivitas yang direncanakan')
            ->assertSeeText('Selesaikan aktivitas');

        $this->actingAs($sales)->patch(route('activities.execute.store', $planned), [
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'result' => 'Customer sudah berhasil dikunjungi.',
        ])->assertSessionHasErrors('attachments');
        $this->assertSame('planned', $planned->fresh()->status);

        $this->actingAs($sales)->patch(route('activities.execute.store', $planned), [
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'detail' => 'Membahas kebutuhan customer.',
            'result' => 'Customer meminta quotation.',
            'attachments' => [UploadedFile::fake()->create('bukti-eksekusi.jpg', 200, 'image/jpeg')],
        ])->assertRedirect(route('customers.show', $customer));

        $planned->refresh();
        $this->assertSame('completed', $planned->status);
        $this->assertSame('Customer meminta quotation.', $planned->result);
        $this->assertSame($initialActivityCount, Activity::count());
        $this->assertDatabaseHas('attachments', [
            'attachable_type' => Activity::class,
            'attachable_id' => $planned->id,
            'name' => 'bukti-eksekusi.jpg',
        ]);

        $cancelled = Activity::create([
            'customer_id' => $customer->id,
            'user_id' => $sales->id,
            'type' => 'meeting',
            'summary' => 'Meeting yang dibatalkan',
            'occurred_at' => now()->addDays(2),
            'status' => 'planned',
        ]);
        $this->actingAs($sales)->patch(route('activities.cancel', $cancelled))
            ->assertSessionHasErrors('cancellation_reason');
        $this->assertSame('planned', $cancelled->fresh()->status);

        $this->actingAs($sales)->patch(route('activities.cancel', $cancelled), [
            'cancellation_reason' => 'Customer meminta jadwal baru.',
        ])->assertRedirect();
        $cancelled->refresh();
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('Customer meminta jadwal baru.', $cancelled->detail);
    }

    public function test_follow_up_can_be_completed_by_recording_activity_or_marking_done(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $customer = $this->customer(['sales_owner_id' => $admin->id, 'created_by' => $admin->id, 'next_follow_up_at' => now()->subHour()]);
        $source = Activity::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'type' => 'call',
            'summary' => 'Hubungi kembali customer',
            'occurred_at' => now()->subDay(),
            'next_follow_up_at' => now()->subHour(),
            'status' => 'completed',
        ]);

        $this->actingAs($admin)->get(route('activities.follow-up', $source))
            ->assertOk()
            ->assertSeeText('Mengerjakan follow-up')
            ->assertSee('name="completes_follow_up_id" value="'.$source->id.'"', false)
            ->assertDontSeeText('Tidak menyelesaikan jadwal follow-up tertentu');
        $this->actingAs($admin)->getJson(route('activities.follow-ups.pending', ['customer_id' => $customer->id]))
            ->assertOk()->assertJsonFragment(['id' => $source->id, 'overdue' => true]);
        $this->actingAs($admin)->get(route('notifications.index'))
            ->assertOk()->assertSeeText('Follow-up terlambat')->assertSeeText('Hubungi kembali customer');

        $nextSchedule = now()->addDay()->format('Y-m-d H:i:s');
        $this->actingAs($admin)->post(route('activities.store'), [
            'customer_id' => $customer->id,
            'type' => 'call',
            'summary' => 'Follow-up telah dilakukan',
            'result' => 'Customer sudah dihubungi',
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'next_follow_up_at' => $nextSchedule,
            'status' => 'completed',
            'completes_follow_up_id' => $source->id,
        ])->assertRedirect(route('customers.show', $customer));

        $source->refresh();
        $this->assertNotNull($source->follow_up_completed_at);
        $this->assertSame($admin->id, $source->follow_up_completed_by);
        $this->assertNotNull($source->follow_up_completion_activity_id);
        $this->assertNotNull($customer->fresh()->next_follow_up_at);

        $manual = Activity::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'type' => 'email',
            'summary' => 'Konfirmasi melalui email',
            'occurred_at' => now()->subDay(),
            'next_follow_up_at' => now()->subMinutes(30),
            'status' => 'completed',
        ]);
        $this->actingAs($admin)->post(route('activities.follow-up.complete', $manual))->assertRedirect();
        $this->assertNotNull($manual->fresh()->follow_up_completed_at);
    }

    public function test_admin_can_change_activity_evidence_policy_per_department(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $sales = Department::create(['code' => 'SLS-POLICY', 'name' => 'Sales', 'activity_evidence_required' => true]);
        $warehouse = Department::create(['code' => 'WHS-POLICY', 'name' => 'Warehouse', 'activity_evidence_required' => false]);

        $this->actingAs($admin)->put(route('settings.activity-evidence.update'), [
            'required_department_ids' => [$warehouse->id],
        ])->assertRedirect();

        $this->assertFalse($sales->fresh()->activity_evidence_required);
        $this->assertTrue($warehouse->fresh()->activity_evidence_required);
    }

    public function test_admin_can_create_and_update_area(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);

        $this->actingAs($admin)->post(route('areas.store'), [
            'code' => 'BLI',
            'name' => 'Bali dan Nusa Tenggara',
            'branch' => 'Denpasar',
            'is_active' => 1,
        ])->assertRedirect(route('areas.index'));

        $area = Area::where('code', 'BLI')->firstOrFail();
        $this->actingAs($admin)->put(route('areas.update', $area), [
            'code' => 'BLI',
            'name' => 'Bali Nusra',
            'branch' => 'Denpasar',
            'is_active' => 0,
        ])->assertRedirect(route('areas.index'));

        $this->assertDatabaseHas('areas', ['id' => $area->id, 'name' => 'Bali Nusra', 'is_active' => false]);
    }

    public function test_formatted_money_input_is_normalized_before_validation(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);

        $this->actingAs($admin)->post(route('customers.store'), [
            'company_name' => 'Customer Format Rupiah',
            'phone' => '081234567890',
            'status' => 'active',
            'credit_limit' => '1.250.000',
            'estimated_monthly_purchase' => '2.750.000',
        ])->assertRedirect();

        $customer = Customer::where('company_name', 'Customer Format Rupiah')->firstOrFail();
        $this->assertSame('1250000.00', $customer->credit_limit);
        $this->assertSame('2750000.00', $customer->estimated_monthly_purchase);
    }

    public function test_opportunity_quantity_uses_integer_format_and_selectable_unit(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $customer = $this->customer(['sales_owner_id' => $admin->id, 'created_by' => $admin->id]);
        $pipeline = Pipeline::create(['name' => 'Quantity Test', 'slug' => 'quantity-test', 'created_by' => $admin->id]);
        PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => 'New', 'slug' => 'new', 'position' => 1, 'probability' => 10]);
        $product = Product::create(['sku' => 'TEST-CTN', 'name' => 'Produk Ctn', 'unit' => 'ctn', 'is_active' => true]);
        $secondProduct = Product::create(['sku' => 'TEST-PACK', 'name' => 'Produk Pack', 'unit' => 'pack', 'is_active' => true]);

        $this->actingAs($admin)->get(route('opportunities.create'))
            ->assertOk()
            ->assertSee('Daftar produk')
            ->assertSee('Tambah produk')
            ->assertDontSee('>Produk</label>', false)
            ->assertSee('Nilai target opportunity')
            ->assertSee('Target Harga')
            ->assertDontSee('Harga penawaran *')
            ->assertSee('Target harga per UOM yang diharapkan customer')
            ->assertSee('Nilai target opportunity')
            ->assertDontSee('>Subtotal</label>', false)
            ->assertSee('Supplier yang digunakan saat ini')
            ->assertSee('Kompetitor');

        $this->actingAs($admin)->post(route('opportunities.store'), [
            'customer_id' => $customer->id,
            'pipeline_id' => $pipeline->id,
            'title' => 'Opportunity dengan satuan',
            'items' => [
                ['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 12000, 'quantity_unit' => 'ctn', 'target_price' => 90, 'unit_price' => 100, 'photo' => UploadedFile::fake()->image('produk-ctn.jpg')],
                ['product_id' => $secondProduct->id, 'product_name' => $secondProduct->name, 'quantity' => 10, 'quantity_unit' => 'pack', 'target_price' => 4500, 'unit_price' => 5000, 'photo' => UploadedFile::fake()->image('produk-pack.png')],
            ],
            'priority' => 'medium',
        ])->assertRedirect();

        $opportunity = Opportunity::where('title', 'Opportunity dengan satuan')->firstOrFail();
        $this->assertSame(12000, $opportunity->estimated_quantity);
        $this->assertSame('ctn', $opportunity->quantity_unit);
        $this->assertSame('1125000.00', $opportunity->estimated_value);
        $this->assertCount(2, $opportunity->items);
        $this->assertTrue($opportunity->items->every(fn ($item) => $item->deal_status === 'on_process'));
        $this->actingAs($admin)->get(route('opportunities.index'))
            ->assertOk()
            ->assertSeeText('Produk Ctn')
            ->assertSeeText('+1 produk lainnya');
        $this->actingAs($admin)->get(route('opportunities.show', $opportunity))
            ->assertOk()
            ->assertSeeText('12.000 Ctn')
            ->assertSeeText('Produk Pack')
            ->assertSeeText('Rp 1.125.000')
            ->assertSeeText('Status barang')
            ->assertSeeText('Diproses')
            ->assertSeeText('Deal')
            ->assertSeeText('Ditolak')
            ->assertSeeText('+ Tambah produk')
            ->assertDontSeeText('Harga hanya diedit di tahap Quotation');

        $item = $opportunity->items->first();
        $this->actingAs($admin)->patch(route('opportunities.items.status', [$opportunity, $item]), [
            'deal_status' => 'deal',
        ])->assertRedirect();

        $this->assertDatabaseHas('opportunity_items', [
            'id' => $item->id,
            'deal_status' => 'deal',
            'deal_status_updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('opportunities.items.store', $opportunity), [
            'product_name' => 'Produk tambahan',
            'quantity' => 2,
            'quantity_unit' => 'pcs',
            'target_price' => 100,
            'unit_price' => 125,
            'photo' => UploadedFile::fake()->image('produk-tambahan.png'),
        ])->assertRedirect();

        $newItem = $opportunity->items()->where('product_name', 'Produk tambahan')->firstOrFail();
        $this->assertSame('on_process', $newItem->deal_status);
        $this->assertSame('250.00', $newItem->subtotal);

        $this->actingAs($admin)->patch(route('opportunities.items.price', [$opportunity, $newItem]), [
            'unit_price' => 150,
        ])->assertRedirect();
        $this->assertDatabaseHas('opportunity_items', ['id' => $newItem->id, 'unit_price' => 150, 'subtotal' => 300]);

        $this->actingAs($admin)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSeeText('Opportunity customer')
            ->assertSeeText('2 diproses')
            ->assertSeeText('1 deal')
            ->assertSeeText('Cust Aktif');
    }

    public function test_sales_created_opportunity_is_forced_to_their_ownership_and_remains_accessible(): void
    {
        $permission = Permission::create(['module' => 'opportunities', 'action' => 'view', 'key' => 'opportunities.view', 'label' => 'View opportunities']);
        $role = Role::create(['name' => 'Sales Opportunity', 'slug' => 'sales']);
        $role->permissions()->attach($permission);
        $sales = User::factory()->create(['name' => 'Sales Pemilik', 'user_type' => 'frontliner', 'authority_level' => 'staff']);
        $supervisor = User::factory()->create(['name' => 'Supervisor Bukan Owner', 'user_type' => 'frontliner', 'authority_level' => 'supervisor']);
        $collaborator = User::factory()->create(['name' => 'Sales Kolaborator', 'user_type' => 'frontliner', 'authority_level' => 'staff']);
        $sales->roles()->attach($role);
        $collaborator->roles()->attach($role);
        $customer = $this->customer(['sales_owner_id' => $sales->id, 'created_by' => $sales->id]);
        $pipeline = Pipeline::create(['name' => 'Owner Test', 'slug' => 'owner-test', 'created_by' => $sales->id]);
        PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => 'New', 'slug' => 'new', 'position' => 1, 'probability' => 10]);

        $this->actingAs($sales)->post(route('opportunities.store'), [
            'customer_id' => $customer->id,
            'pipeline_id' => $pipeline->id,
            'owner_id' => $supervisor->id,
            'title' => 'Opportunity tetap milik sales login',
            'estimated_value' => 1000000,
            'photo' => UploadedFile::fake()->image('produk-awal.jpg'),
            'priority' => 'medium',
            'participant_ids' => [$collaborator->id],
        ])->assertRedirect();

        $opportunity = Opportunity::where('title', 'Opportunity tetap milik sales login')->firstOrFail();
        $this->assertSame($sales->id, $opportunity->owner_id);
        $this->assertContains($collaborator->id, $opportunity->participants);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $collaborator->id,
            'type' => 'opportunity_invitation',
        ]);
        $this->actingAs($sales)->get(route('opportunities.show', $opportunity))->assertOk();
        $this->actingAs($collaborator)->get(route('opportunities.show', $opportunity))->assertOk();
    }

    public function test_seeded_workspace_detail_and_form_pages_render(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('authority_level', 'master_admin')->firstOrFail();
        $customer = Customer::firstOrFail();
        $lead = \App\Models\Lead::firstOrFail();
        $opportunity = Opportunity::firstOrFail();
        $pipeline = Pipeline::firstOrFail();

        foreach ([
            route('leads.create'),
            route('leads.edit', $lead),
            route('customers.create'),
            route('customers.show', $customer),
            route('customers.edit', $customer),
            route('opportunities.create'),
            route('opportunities.show', $opportunity),
            route('opportunities.kanban', ['pipeline' => $pipeline]),
            route('activities.create', ['customer' => $customer]),
            route('tasks.create', ['customer' => $customer]),
            route('pipelines.edit', $pipeline),
            route('reports.index'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_conversion_report_can_be_read_and_filtered_by_lead_source_without_export(): void
    {
        $admin = User::factory()->create([
            'authority_level' => 'master_admin',
            'is_active' => true,
        ]);

        \App\Models\Lead::create([
            'company_name' => 'Lead Referral Report',
            'contact_name' => 'Referral PIC',
            'phone' => '081200000001',
            'source' => 'referral',
            'status' => 'cold_lead',
            'owner_id' => $admin->id,
            'created_by' => $admin->id,
        ]);
        \App\Models\Lead::create([
            'company_name' => 'Lead Event Report',
            'contact_name' => 'Event PIC',
            'phone' => '081200000002',
            'source' => 'event',
            'status' => 'cold_lead',
            'owner_id' => $admin->id,
            'created_by' => $admin->id,
        ]);
        \App\Models\Lead::create([
            'company_name' => 'Lead Adds Report',
            'contact_name' => 'Lead Adds PIC',
            'phone' => '081200000003',
            'source' => 'database',
            'status' => 'leads_adds',
            'owner_id' => $admin->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.index', ['view' => 'conversion']))
            ->assertOk()
            ->assertSee('Referral')
            ->assertSee('Event')
            ->assertSee('Sumber lead');

        $this->actingAs($admin)
            ->get(route('reports.index', ['view' => 'conversion', 'source' => 'referral']))
            ->assertOk()
            ->assertSee('Lead Referral Report')
            ->assertDontSee('Lead Event Report');

        $this->actingAs($admin)
            ->get(route('reports.index', [
                'view' => 'conversion',
                'lead_status' => 'leads_adds',
            ]))
            ->assertOk()
            ->assertSee('Leads Adds')
            ->assertSee('Lead Adds Report')
            ->assertDontSee('Lead Event Report');
    }

    public function test_customer_workspace_combines_prospects_and_customers_without_mixing_their_data(): void
    {
        $admin = User::factory()->create([
            'authority_level' => 'master_admin',
            'is_active' => true,
        ]);
        $lead = \App\Models\Lead::create([
            'company_name' => 'Lead Gabungan Test',
            'contact_name' => 'Kontak Lead',
            'phone' => '081211111111',
            'source' => 'referral',
            'status' => 'cold_lead',
            'owner_id' => $admin->id,
            'created_by' => $admin->id,
        ]);
        $customer = $this->customer([
            'company_name' => 'Customer Gabungan Test',
            'sales_owner_id' => $admin->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('customers.index', ['view' => 'prospects']))
            ->assertOk()
            ->assertSee('Lead dan customer ada di satu tempat')
            ->assertSee($lead->company_name)
            ->assertSee('Jadikan customer')
            ->assertDontSee($customer->customer_id);

        $this->actingAs($admin)
            ->get(route('customers.index', ['view' => 'customers']))
            ->assertOk()
            ->assertSee($customer->company_name)
            ->assertSee($customer->customer_id)
            ->assertDontSee($lead->lead_id);
    }

    public function test_customer_workspace_filters_leads_and_customers_by_customer_type(): void
    {
        $admin = User::factory()->create([
            'authority_level' => 'master_admin',
            'is_active' => true,
        ]);
        $cafe = \App\Models\BusinessUnit::create(['code' => 'FILTER-CAFE', 'name' => 'Cafe Filter Test']);
        $hotel = \App\Models\BusinessUnit::create(['code' => 'FILTER-HOTEL', 'name' => 'Hotel Filter Test']);

        foreach ([
            ['company_name' => 'Lead Cafe Terpilih', 'business_unit_id' => $cafe->id, 'business_type' => $cafe->name],
            ['company_name' => 'Lead Hotel Tidak Terpilih', 'business_unit_id' => $hotel->id, 'business_type' => $hotel->name],
        ] as $index => $data) {
            \App\Models\Lead::create($data + [
                'contact_name' => 'Kontak '.($index + 1),
                'phone' => '08120000000'.($index + 1),
                'source' => 'referral',
                'status' => 'cold_lead',
                'owner_id' => $admin->id,
                'created_by' => $admin->id,
            ]);
        }

        $this->customer([
            'company_name' => 'Customer Cafe Terpilih',
            'business_unit_id' => $cafe->id,
            'business_type' => $cafe->name,
            'sales_owner_id' => $admin->id,
            'created_by' => $admin->id,
        ]);
        $this->customer([
            'company_name' => 'Customer Hotel Tidak Terpilih',
            'business_unit_id' => $hotel->id,
            'business_type' => $hotel->name,
            'sales_owner_id' => $admin->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('customers.index', ['view' => 'prospects', 'business_type' => $cafe->name]))
            ->assertOk()
            ->assertSee('Jenis Customer')
            ->assertSee('Lead Cafe Terpilih')
            ->assertDontSee('Lead Hotel Tidak Terpilih');

        $this->actingAs($admin)
            ->get(route('customers.index', ['view' => 'customers', 'business_type' => $cafe->name]))
            ->assertOk()
            ->assertSee('Customer Cafe Terpilih')
            ->assertDontSee('Customer Hotel Tidak Terpilih');
    }

    public function test_lead_form_shows_complete_fields_and_current_statuses(): void
    {
        $admin = User::factory()->create([
            'authority_level' => 'master_admin',
            'is_active' => true,
        ]);
        foreach (['Multi Chain & Franchise', 'Resto / Rumah Makan', 'Cafe, Jus Bar, Matcha House & Bar', 'Hotel', 'Distributor', 'Food Industry', 'Modern Trade Nasional'] as $index => $name) {
            \App\Models\BusinessUnit::create(['code' => 'FORM-LEAD-'.($index + 1), 'name' => $name]);
        }

        $this->actingAs($admin)
            ->get(route('leads.create'))
            ->assertOk()
            ->assertSee('Nama perusahaan')
            ->assertSee('Nama PIC *')
            ->assertSee('Nomor WhatsApp')
            ->assertDontSee('>WhatsApp</label>', false)
            ->assertSee('Kualifikasi')
            ->assertSee('Produk yang diminati')
            ->assertSee('Tambah produk')
            ->assertSee('Est. Qty/Bulan')
            ->assertDontSee('>Segment</label>', false)
            ->assertSee('Jenis customer')
            ->assertSee('Multi Chain &amp; Franchise', false)
            ->assertSee('Resto / Rumah Makan', false)
            ->assertSee('Cafe, Jus Bar, Matcha House &amp; Bar', false)
            ->assertSee('Hotel')
            ->assertSee('Distributor')
            ->assertSee('Food Industry')
            ->assertSee('Modern Trade Nasional')
            ->assertDontSee('+ Tambah lainnya')
            ->assertSee('Sales penanggung jawab')
            ->assertDontSee('>Team</label>', false)
            ->assertSee('Kota/Kabupaten')
            ->assertSee('Pilih area')
            ->assertSee('Alamat lengkap')
            ->assertSee('>Area</label>', false)
            ->assertSee('Leads Adds')
            ->assertSee('Leads Cold')
            ->assertSee('Leads Warm')
            ->assertSee('Leads On Hold')
            ->assertSee('Leads Risky')
            ->assertDontSee('Cust Top')
            ->assertDontSee('Cust Mid')
            ->assertDontSee('Cust Hold');
    }

    public function test_customer_form_is_compact_and_avoids_repeated_classification_fields(): void
    {
        $admin = User::factory()->create([
            'authority_level' => 'master_admin',
            'is_active' => true,
        ]);
        \App\Models\BusinessUnit::create(['code' => 'FORM-CUST-1', 'name' => 'Cafe, Jus Bar, Matcha House & Bar']);
        \App\Models\BusinessUnit::create(['code' => 'FORM-CUST-2', 'name' => 'Resto / Rumah Makan']);

        $this->actingAs($admin)
            ->get(route('customers.create'))
            ->assertOk()
            ->assertSee('Informasi customer')
            ->assertSee('Nomor WhatsApp')
            ->assertSee('Kota/Kabupaten')
            ->assertSee('>Area</label>', false)
            ->assertSee('Jenis customer')
            ->assertSee('Cafe, Jus Bar, Matcha House &amp; Bar', false)
            ->assertSee('Resto / Rumah Makan', false)
            ->assertDontSee('+ Tambah lainnya')
            ->assertSee('Alamat lengkap')
            ->assertDontSee('Alamat pengiriman')
            ->assertDontSee('Alamat penagihan')
            ->assertSee('Informasi transaksi')
            ->assertSee('Sales tambahan')
            ->assertDontSee('>Industry</label>', false)
            ->assertDontSee('>Segment</label>', false)
            ->assertDontSee('>Customer group</label>', false)
            ->assertDontSee('Customer potential')
            ->assertDontSee('Product category');
    }

    public function test_custom_customer_type_is_saved_and_available_for_reuse(): void
    {
        $resolver = app(\App\Support\BusinessUnitResolver::class);

        $customerType = $resolver->resolve('Other', 'Kantin Sekolah');

        $this->assertDatabaseHas('business_units', [
            'id' => $customerType->id,
            'name' => 'Kantin Sekolah',
            'is_active' => true,
        ]);
        $this->assertTrue($resolver->options()->contains('name', 'Kantin Sekolah'));

        try {
            $resolver->resolve('Jenis Tanpa Izin', null, false);
            $this->fail('Pengguna non-admin tidak boleh membuat jenis customer baru.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('business_type', $exception->errors());
        }

        $this->assertDatabaseMissing('business_units', ['name' => 'Jenis Tanpa Izin']);
    }

    public function test_master_admin_can_manage_customer_types_safely_from_settings(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin', 'is_active' => true]);
        $used = \App\Models\BusinessUnit::create(['code' => 'USED-TYPE', 'name' => 'Jenis Terpakai']);
        $unused = \App\Models\BusinessUnit::create(['code' => 'FREE-TYPE', 'name' => 'Jenis Belum Dipakai']);
        \App\Models\Lead::create([
            'company_name' => 'Lead Settings Test',
            'contact_name' => 'PIC Settings',
            'phone' => '081299999991',
            'source' => 'referral',
            'business_unit_id' => $used->id,
            'business_type' => $used->name,
            'status' => 'cold_lead',
            'owner_id' => $admin->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('settings.customer-types.index'))
            ->assertOk()
            ->assertSee('Jenis Customer')
            ->assertSee('Jenis Terpakai')
            ->assertSee('Jenis Belum Dipakai');

        $this->actingAs($admin)->post(route('settings.customer-types.store'), ['name' => 'Jenis Baru Settings'])
            ->assertRedirect();
        $this->assertDatabaseHas('business_units', ['name' => 'Jenis Baru Settings', 'is_active' => true]);

        $this->actingAs($admin)->put(route('settings.customer-types.update', $used), ['name' => 'Jenis Terpakai Baru'])
            ->assertRedirect();
        $this->assertDatabaseHas('leads', ['business_unit_id' => $used->id, 'business_type' => 'Jenis Terpakai Baru']);

        $this->actingAs($admin)->patch(route('settings.customer-types.toggle', $used))->assertRedirect();
        $this->assertDatabaseHas('business_units', ['id' => $used->id, 'is_active' => false]);

        $this->actingAs($admin)->delete(route('settings.customer-types.destroy', $used))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertDatabaseHas('business_units', ['id' => $used->id]);

        $this->actingAs($admin)->delete(route('settings.customer-types.destroy', $unused))->assertRedirect();
        $this->assertDatabaseMissing('business_units', ['id' => $unused->id]);
    }

    public function test_csa_can_assign_lead_to_sales_and_business_unit_is_filled_automatically(): void
    {
        $admin = User::factory()->create([
            'authority_level' => 'master_admin',
            'is_active' => true,
        ]);
        $leadView = Permission::create(['module' => 'leads', 'action' => 'view', 'key' => 'leads.view', 'label' => 'View leads']);
        $customerView = Permission::create(['module' => 'customers', 'action' => 'view', 'key' => 'customers.view', 'label' => 'View customers']);
        $opportunityView = Permission::create(['module' => 'opportunities', 'action' => 'view', 'key' => 'opportunities.view', 'label' => 'View opportunities']);
        $csaRole = Role::create(['name' => 'CSA', 'slug' => 'csa']);
        $csaRole->permissions()->attach([$leadView->id, $customerView->id, $opportunityView->id]);
        $csa = User::factory()->create([
            'user_type' => 'backliner',
            'authority_level' => 'staff',
            'is_active' => true,
        ]);
        $csa->roles()->attach($csaRole);
        $salesRole = Role::create(['name' => 'Sales', 'slug' => 'sales']);
        $sales = User::factory()->create([
            'user_type' => 'frontliner',
            'authority_level' => 'staff',
            'is_active' => true,
        ]);
        $sales->roles()->attach($salesRole);
        $businessUnit = \App\Models\BusinessUnit::create(['code' => 'BU-TEST', 'name' => 'Business Unit Test']);
        $sales->businessUnits()->attach($businessUnit);
        $lead = \App\Models\Lead::create([
            'company_name' => 'Lead Owner Test',
            'contact_name' => 'PIC Test',
            'phone' => '081222222222',
            'source' => 'whatsapp',
            'business_type' => 'Cafe, Jus Bar, Matcha House & Bar',
            'product_interest' => 'Paper cup custom',
            'product_interests' => [
                ['product_name' => 'Paper cup custom', 'estimated_need' => 12000, 'estimated_need_unit' => 'ctn'],
                ['product_name' => 'Lid dome 12 OZ', 'estimated_need' => 6000, 'estimated_need_unit' => 'pcs'],
            ],
            'estimated_need' => 12000,
            'estimated_need_unit' => 'ctn',
            'status' => 'cold_lead',
            'owner_id' => $admin->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($csa)
            ->put(route('leads.update', $lead), [
                'company_name' => $lead->company_name,
                'contact_name' => $lead->contact_name,
                'phone' => $lead->phone,
                'source' => $lead->source,
                'business_unit_id' => $businessUnit->id,
                'status' => 'warm_lead',
                'owner_id' => $sales->id,
            ])
            ->assertRedirect(route('customers.index', ['view' => 'prospects']));

        $this->assertSame($sales->id, $lead->fresh()->owner_id);
        $this->assertSame($businessUnit->id, $lead->fresh()->business_unit_id);
        $this->assertSame('warm_lead', $lead->fresh()->status);
        $this->assertSame($lead->phone, $lead->fresh()->whatsapp);

        $this->actingAs($csa)->post(route('leads.convert', $lead))
            ->assertSessionHasErrors(['legal_name', 'npwp']);
        $this->assertNull(Customer::where('converted_from_lead_id', $lead->id)->first());

        $this->actingAs($csa)->post(route('leads.convert', $lead), [
            'legal_name' => 'PT Lead Owner Test',
            'npwp' => '12.345.678.9-012.000',
        ])->assertRedirect();
        $customer = Customer::where('converted_from_lead_id', $lead->id)->firstOrFail();
        $this->assertSame('PT Lead Owner Test', $customer->legal_name);
        $this->assertSame('12.345.678.9-012.000', $customer->npwp);
        $this->assertSame($businessUnit->name, $customer->business_type);
        $this->assertSame($lead->product_interest, $customer->product_interest);
        $this->assertSame($lead->estimated_need, $customer->estimated_need);
        $this->assertSame('ctn', $customer->estimated_need_unit);
        $this->assertCount(2, $customer->product_interests);
        $this->assertSame('Lid dome 12 OZ', $customer->product_interests[1]['product_name']);
        $this->assertSame(0, Opportunity::count());

        $this->actingAs($csa)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Kebutuhan awal')
            ->assertSee('Buat opportunity dari kebutuhan ini');

        $this->actingAs($csa)
            ->get(route('opportunities.create', ['customer' => $customer, 'from_initial_need' => 1]))
            ->assertOk()
            ->assertSee($lead->product_interest)
            ->assertSee('Lid dome 12 OZ')
            ->assertSee((string) $lead->estimated_need);
        $this->assertStringContainsString('ctn', $this->actingAs($csa)->get(route('opportunities.create', ['customer' => $customer, 'from_initial_need' => 1]))->getContent());
        $this->assertSame(0, Opportunity::count());
    }

    public function test_sales_can_create_own_lead_and_customer_while_submitted_owner_is_ignored(): void
    {
        $leadView = Permission::create(['module' => 'leads', 'action' => 'view', 'key' => 'leads.view', 'label' => 'View leads']);
        $customerView = Permission::create(['module' => 'customers', 'action' => 'view', 'key' => 'customers.view', 'label' => 'View customers']);
        $salesRole = Role::create(['name' => 'Sales Create Own', 'slug' => 'sales']);
        $salesRole->permissions()->attach([$leadView->id, $customerView->id]);
        $sales = User::factory()->create(['user_type' => 'frontliner', 'authority_level' => 'staff', 'is_active' => true]);
        $otherSales = User::factory()->create(['user_type' => 'frontliner', 'authority_level' => 'staff', 'is_active' => true]);
        $sales->roles()->attach($salesRole);
        $businessUnit = \App\Models\BusinessUnit::create(['code' => 'OWN', 'name' => 'Own Sales Unit']);
        $sales->businessUnits()->attach($businessUnit);

        $this->actingAs($sales)
            ->post(route('leads.store'), [
                'company_name' => 'Lead Buatan Sales',
                'contact_name' => 'Pemilik Lead',
                'phone' => '081234567890',
                'source' => 'sales_visit',
                'status' => 'cold_lead',
                'owner_id' => $otherSales->id,
            ])
            ->assertRedirect(route('customers.index', ['view' => 'prospects']));

        $lead = \App\Models\Lead::where('company_name', 'Lead Buatan Sales')->firstOrFail();
        $this->assertSame($sales->id, $lead->owner_id);
        $this->assertSame($businessUnit->id, $lead->business_unit_id);

        $this->actingAs($sales)
            ->post(route('customers.store'), [
                'company_name' => 'Customer Buatan Sales',
                'phone' => '081298765432',
                'status' => 'active',
                'sales_owner_id' => $otherSales->id,
                'assigned_user_ids' => [$otherSales->id],
            ])
            ->assertRedirect();

        $customer = Customer::where('company_name', 'Customer Buatan Sales')->firstOrFail();
        $this->assertSame($sales->id, $customer->sales_owner_id);
        $this->assertEquals([$sales->id], $customer->assignedUsers()->pluck('users.id')->all());
    }

    public function test_inactive_customer_cannot_receive_a_new_activity(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $customer = $this->customer([
            'status' => 'inactive',
            'sales_owner_id' => $admin->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('activities.create'))
            ->assertOk()
            ->assertDontSee('<option value="'.$customer->id.'">', false);

        $this->actingAs($admin)
            ->post(route('activities.store'), [
                'customer_id' => $customer->id,
                'type' => 'intro_contact',
                'summary' => 'Aktivitas tidak boleh dibuat',
                'occurred_at' => now()->format('Y-m-d H:i:s'),
                'status' => 'completed',
            ])
            ->assertSessionHasErrors('customer_id');

        $this->assertDatabaseMissing('activities', [
            'customer_id' => $customer->id,
            'summary' => 'Aktivitas tidak boleh dibuat',
        ]);

        $this->actingAs($admin)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('title="Aktifkan customer untuk mencatat aktivitas"', false);
    }

    public function test_regular_activity_without_result_is_not_presented_as_waiting_for_approval(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $customer = $this->customer(['sales_owner_id' => $admin->id, 'created_by' => $admin->id]);
        $activity = Activity::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'type' => 'visit',
            'summary' => 'Visit biasa tanpa hasil',
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('activities.index', ['activity' => $activity->id]))
            ->assertOk()
            ->assertSeeText('Tidak diisi')
            ->assertDontSeeText('Menunggu keputusan approver');
    }

    public function test_task_cannot_enter_review_without_an_explicit_reviewer(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $assignee = User::factory()->create(['is_active' => true]);
        $task = Task::create([
            'title' => 'Task yang perlu pemeriksaan',
            'created_by' => $admin->id,
            'priority' => 'medium',
            'status' => 'in_progress',
        ]);
        $task->assignees()->attach($assignee);

        $this->actingAs($admin)
            ->patch(route('tasks.status', $task), ['status' => 'review'])
            ->assertSessionHasErrors('status');
        $this->assertSame('in_progress', $task->fresh()->status);

        $task->update(['reviewer_id' => $admin->id]);
        $this->actingAs($admin)
            ->patch(route('tasks.status', $task), ['status' => 'review'])
            ->assertSessionHasNoErrors();
        $this->assertSame('review', $task->fresh()->status);
    }

    public function test_customer_documents_accept_pdf_and_jpg_and_render_in_preview_list(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        $customer = $this->customer(['sales_owner_id' => $admin->id, 'created_by' => $admin->id]);

        foreach ([
            UploadedFile::fake()->create('dokumen-kontrak.pdf', 50, 'application/pdf'),
            UploadedFile::fake()->image('foto-dokumen.jpg'),
        ] as $document) {
            $this->actingAs($admin)
                ->post(route('customers.documents.store', $customer), ['supporting_document' => $document])
                ->assertRedirect(route('customers.show', $customer));
        }

        $this->actingAs($admin)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSeeText('dokumen-kontrak.pdf')
            ->assertSeeText('foto-dokumen.jpg');
    }

    public function test_report_csv_export_honors_the_active_search_filter(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);
        Lead::create(['company_name' => 'Alpha Filter Export', 'contact_name' => 'Kontak Alpha', 'phone' => '081200000001', 'owner_id' => $admin->id, 'created_by' => $admin->id, 'status' => 'cold_lead']);
        Lead::create(['company_name' => 'Beta Tidak Diekspor', 'contact_name' => 'Kontak Beta', 'phone' => '081200000002', 'owner_id' => $admin->id, 'created_by' => $admin->id, 'status' => 'warm_lead']);

        $response = $this->actingAs($admin)->get(route('reports.export.csv', ['search' => 'Alpha Filter']));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Alpha Filter Export', $csv);
        $this->assertStringNotContainsString('Beta Tidak Diekspor', $csv);
    }

    public function test_settings_submenu_has_responsive_navigation_hooks(): void
    {
        $admin = User::factory()->create(['authority_level' => 'master_admin']);

        $this->actingAs($admin)->get(route('customers.index'))
            ->assertOk()
            ->assertSee('data-settings-nav', false)
            ->assertSee('data-settings-submenu', false)
            ->assertSeeText('Jenis Customer')
            ->assertSeeText('Kebijakan Bukti');
    }

    private function customer(array $overrides = []): Customer
    {
        return Customer::create($overrides + ['company_name' => 'PT Customer Test', 'phone' => '081200000000', 'status' => 'active']);
    }
}
