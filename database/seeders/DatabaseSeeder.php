<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Area;
use App\Models\BusinessUnit;
use App\Models\CrmNotification;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Pipeline;
use App\Models\Product;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            'dashboard' => ['view'],
            'leads' => ['view', 'create', 'edit', 'convert'],
            'customers' => ['view', 'create', 'edit', 'invite'],
            'opportunities' => ['view', 'create', 'edit', 'move_stage'],
            'activities' => ['view', 'create'],
            'tasks' => ['view', 'create', 'update'],
            'approvals' => ['view', 'create', 'decide'],
            'reports' => ['view'],
            'admin' => ['manage'],
        ])->flatMap(fn ($actions, $module) => collect($actions)->map(fn ($action) => Permission::create(['module' => $module, 'action' => $action, 'key' => "$module.$action", 'label' => ucfirst($action).' '.ucfirst($module)])));

        $roles = collect([
            'master_admin' => ['dashboard', 'leads', 'customers', 'opportunities', 'activities', 'tasks', 'approvals', 'reports', 'admin'],
            'sales' => ['dashboard', 'leads', 'customers', 'opportunities', 'activities', 'tasks', 'approvals'],
            'csa' => ['dashboard', 'leads', 'customers', 'opportunities', 'activities', 'tasks', 'reports'],
            'sales_supervisor' => ['dashboard', 'leads', 'customers', 'opportunities', 'activities', 'tasks', 'approvals', 'reports'],
            'sales_manager' => ['dashboard', 'leads', 'customers', 'opportunities', 'activities', 'tasks', 'approvals', 'reports'],
            'purchasing' => ['dashboard', 'customers', 'tasks', 'approvals'],
            'finance' => ['dashboard', 'customers', 'tasks', 'approvals', 'reports'],
            'warehouse' => ['dashboard', 'customers', 'tasks', 'activities'],
        ])->mapWithKeys(function ($modules, $slug) use ($permissions) {
            $role = Role::updateOrCreate(['slug' => $slug], ['name' => ucwords(str_replace('_', ' ', $slug)), 'description' => 'System role for '.str_replace('_', ' ', $slug), 'is_system' => true]);
            $role->permissions()->sync($permissions->whereIn('module', $modules)->pluck('id'));
            return [$slug => $role];
        });

        $roles['csa']->update(['parent_role_id' => $roles['sales']->id]);
        $roles['sales_supervisor']->update(['parent_role_id' => $roles['csa']->id]);
        $roles['sales_manager']->update(['parent_role_id' => $roles['sales_supervisor']->id]);

        $this->call(BusinessUnitSeeder::class);
        $food = BusinessUnit::query()->where('name', 'Resto / Rumah Makan')->firstOrFail();
        $creative = BusinessUnit::query()->where('name', 'Other')->firstOrFail();
        $salesDept = Department::create(['business_unit_id' => $food->id, 'code' => 'SLS', 'name' => 'Sales', 'is_frontliner' => true, 'activity_evidence_required' => true]);
        $financeDept = Department::create(['business_unit_id' => $food->id, 'code' => 'FIN', 'name' => 'Finance']);
        $purchasingDept = Department::create(['business_unit_id' => $food->id, 'code' => 'PUR', 'name' => 'Purchasing']);
        $warehouseDept = Department::create(['business_unit_id' => $food->id, 'code' => 'WHS', 'name' => 'Warehouse']);
        $areas = collect([
            Area::create(['code' => 'JKT', 'name' => 'Jabodetabek', 'branch' => 'Jakarta']),
            Area::create(['code' => 'JBR', 'name' => 'Jawa Barat', 'branch' => 'Bandung']),
            Area::create(['code' => 'JTM', 'name' => 'Jawa Timur', 'branch' => 'Surabaya']),
        ]);

        $admin = User::create(['employee_id' => 'USR-0001', 'name' => 'Master Administrator', 'email' => 'admin@unified.test', 'phone' => '081200000001', 'password' => 'password', 'authority_level' => 'master_admin', 'user_type' => 'backliner', 'is_approver' => true]);
        $manager = User::create(['employee_id' => 'USR-0002', 'name' => 'Maya Sales Manager', 'email' => 'manager@unified.test', 'phone' => '081200000002', 'password' => 'password', 'authority_level' => 'manager', 'user_type' => 'frontliner', 'is_approver' => true]);
        $supervisor = User::create(['employee_id' => 'USR-0003', 'name' => 'Raka Supervisor', 'email' => 'supervisor@unified.test', 'phone' => '081200000003', 'password' => 'password', 'authority_level' => 'supervisor', 'user_type' => 'frontliner', 'is_approver' => true, 'manager_id' => $manager->id]);
        $csa = User::create(['employee_id' => 'USR-0004', 'name' => 'Citra CSA', 'email' => 'csa@unified.test', 'phone' => '081200000004', 'password' => 'password', 'authority_level' => 'supervisor', 'user_type' => 'frontliner', 'is_approver' => true, 'manager_id' => $manager->id]);
        $salesA = User::create(['employee_id' => 'USR-0005', 'name' => 'Nadia Sales', 'email' => 'sales@unified.test', 'phone' => '081200000005', 'password' => 'password', 'authority_level' => 'staff', 'user_type' => 'frontliner', 'manager_id' => $csa->id]);
        $salesB = User::create(['employee_id' => 'USR-0006', 'name' => 'Iky Account Executive', 'email' => 'iky@unified.test', 'phone' => '081200000006', 'password' => 'password', 'authority_level' => 'staff', 'user_type' => 'frontliner', 'manager_id' => $csa->id]);
        $finance = User::create(['employee_id' => 'USR-0007', 'name' => 'Sinta Finance', 'email' => 'finance@unified.test', 'phone' => '081200000007', 'password' => 'password', 'authority_level' => 'staff', 'user_type' => 'backliner']);
        $purchasing = User::create(['employee_id' => 'USR-0008', 'name' => 'Bima Purchasing', 'email' => 'purchasing@unified.test', 'phone' => '081200000008', 'password' => 'password', 'authority_level' => 'staff', 'user_type' => 'backliner']);
        $warehouse = User::create(['employee_id' => 'USR-0009', 'name' => 'Doni Warehouse', 'email' => 'warehouse@unified.test', 'phone' => '081200000009', 'password' => 'password', 'authority_level' => 'staff', 'user_type' => 'backliner']);

        foreach ([$manager, $supervisor, $salesA, $salesB] as $user) {
            $user->businessUnits()->attach($food);
            $user->departments()->attach($salesDept);
            $user->areas()->sync($areas->pluck('id'));
        }
        foreach ([[$admin, 'master_admin'], [$manager, 'sales_manager'], [$supervisor, 'sales_supervisor'], [$salesA, 'sales'], [$salesB, 'sales'], [$csa, 'csa'], [$finance, 'finance'], [$purchasing, 'purchasing'], [$warehouse, 'warehouse']] as [$user, $role]) $user->roles()->attach($roles[$role]);
        $csa->businessUnits()->attach($food);
        $csa->areas()->sync($areas->pluck('id'));
        foreach ([[$finance, $financeDept], [$purchasing, $purchasingDept], [$warehouse, $warehouseDept]] as [$user, $department]) {
            $user->businessUnits()->attach($food); $user->departments()->attach($department);
        }

        $products = collect([
            Product::create(['sku' => 'PRD-001', 'name' => 'Paper Bowl 650 ML', 'category' => 'Paper Bowl', 'unit' => 'pcs', 'base_price' => 1650]),
            Product::create(['sku' => 'PRD-002', 'name' => 'Paper Cup 5 OZ DPE', 'category' => 'Paper Cup', 'unit' => 'pcs', 'base_price' => 475]),
            Product::create(['sku' => 'PRD-003', 'name' => 'PPI Thinwall Bento 4 Sekat Flat V', 'category' => 'Thinwall', 'unit' => 'pcs', 'base_price' => 2850]),
        ]);

        $horeka = Pipeline::create(['name' => 'Sales Pipeline HOREKA', 'slug' => 'horeka', 'description' => 'Pipeline customer hotel, restaurant, dan cafe.', 'business_unit_id' => $food->id, 'is_active' => true, 'created_by' => $admin->id]);
        $horekaStages = $this->stages($horeka, [
            ['New Lead', 10, '#64748b'], ['Qualified', 25, '#3b82f6'], ['Need Analysis', 40, '#8b5cf6'],
            ['Sample', 55, '#06b6d4'], ['Quotation', 65, '#f59e0b'], ['Negotiation', 80, '#f97316'],
            ['Closed Won', 100, '#10b981', true], ['Closed Lost', 0, '#ef4444', false, true],
        ]);
        $horekaStages[4]->rules()->createMany([
            ['rule_type' => 'field', 'field_key' => 'product_name', 'label' => 'Produk wajib dipilih'],
            ['rule_type' => 'follow_up', 'label' => 'Next follow-up wajib ditentukan'],
        ]);
        $horekaStages[5]->rules()->createMany([
            ['rule_type' => 'field', 'field_key' => 'offered_price', 'label' => 'Harga penawaran wajib diisi'],
        ]);

        $project = Pipeline::create(['name' => 'Custom Project Pipeline', 'slug' => 'custom-project', 'description' => 'Pipeline proyek desain dan produksi custom.', 'business_unit_id' => $creative->id, 'is_active' => true, 'created_by' => $admin->id]);
        $projectStages = $this->stages($project, [
            ['Inquiry', 10, '#64748b'], ['Design Brief', 25, '#8b5cf6'], ['Costing', 40, '#3b82f6'],
            ['Sample Development', 55, '#06b6d4'], ['Customer Review', 65, '#f59e0b'], ['Final Approval', 80, '#f97316'],
            ['PO', 90, '#14b8a6'], ['Completed', 100, '#10b981', true], ['Cancelled', 0, '#ef4444', false, true],
        ]);

        $leads = collect([
            Lead::create(['company_name' => 'Kopi Ruang Kota', 'brand_name' => 'Ruang Kopi', 'contact_name' => 'Aditya', 'phone' => '081311110001', 'email' => 'adit@ruangkopi.test', 'city' => 'Jakarta', 'address' => 'Jl. Senopati No. 18, Jakarta Selatan', 'area_id' => $areas[0]->id, 'business_unit_id' => $food->id, 'owner_id' => $salesA->id, 'source' => 'event', 'product_interest' => 'Food Packaging', 'estimated_need' => 12000, 'estimated_need_unit' => 'pcs', 'notes' => 'Membutuhkan kemasan untuk rencana outlet baru.', 'status' => 'warm_lead', 'next_follow_up_at' => now()->addDay(), 'created_by' => $salesA->id]),
            Lead::create(['company_name' => 'Nusa Dining Group', 'contact_name' => 'Clarissa', 'phone' => '081311110002', 'city' => 'Bandung', 'address' => 'Jl. Riau No. 25, Bandung', 'area_id' => $areas[1]->id, 'business_unit_id' => $food->id, 'owner_id' => $salesB->id, 'source' => 'referral', 'product_interest' => 'Custom Packaging', 'estimated_need' => 5000, 'estimated_need_unit' => 'ctn', 'notes' => 'Memerlukan pembahasan kebutuhan custom packaging.', 'status' => 'warm_lead', 'next_follow_up_at' => now()->addDays(2), 'created_by' => $salesB->id]),
        ]);

        $customers = collect([
            Customer::create(['company_name' => 'PT Arunika Hospitality Group', 'brand_name' => 'Arunika Suites', 'phone' => '0215551101', 'email' => 'procurement@arunikasuites.test', 'city' => 'Jakarta', 'area_id' => $areas[0]->id, 'business_unit_id' => $food->id, 'business_type' => 'Multi Chain & Franchise', 'sales_owner_id' => $salesA->id, 'supervisor_id' => $supervisor->id, 'manager_id' => $manager->id, 'status' => 'pareto', 'credit_limit' => 250000000, 'payment_term_days' => 30, 'estimated_monthly_purchase' => 85000000, 'next_follow_up_at' => now()->addDay(), 'created_by' => $salesA->id]),
            Customer::create(['company_name' => 'CV Rasa Nusantara', 'brand_name' => 'Dapur Rasa', 'phone' => '0225551102', 'email' => 'owner@dapurrasa.test', 'city' => 'Bandung', 'area_id' => $areas[1]->id, 'business_unit_id' => $food->id, 'business_type' => 'Resto / Rumah Makan', 'sales_owner_id' => $salesB->id, 'supervisor_id' => $supervisor->id, 'manager_id' => $manager->id, 'status' => 'active', 'payment_term_days' => 14, 'estimated_monthly_purchase' => 45000000, 'next_follow_up_at' => now()->subHours(4), 'created_by' => $salesB->id]),
            Customer::create(['company_name' => 'Lumina Creative Studio', 'brand_name' => 'Lumina Events', 'phone' => '0315551103', 'email' => 'hello@luminaevents.test', 'city' => 'Surabaya', 'area_id' => $areas[2]->id, 'business_unit_id' => $creative->id, 'business_type' => 'Other', 'sales_owner_id' => $salesA->id, 'supervisor_id' => $supervisor->id, 'manager_id' => $manager->id, 'status' => 'risky', 'next_follow_up_at' => now()->addDays(3), 'created_by' => $salesA->id]),
        ]);
        $customers[0]->assignedUsers()->attach([$salesA->id => ['responsibility' => 'owner'], $supervisor->id => ['responsibility' => 'supervisor']]);
        $customers[1]->assignedUsers()->attach([$salesB->id => ['responsibility' => 'owner'], $supervisor->id => ['responsibility' => 'supervisor']]);
        $customers[2]->assignedUsers()->attach([$salesA->id => ['responsibility' => 'owner']]);

        $opportunities = collect([
            Opportunity::create(['customer_id' => $customers[0]->id, 'pipeline_id' => $horeka->id, 'pipeline_stage_id' => $horekaStages[4]->id, 'owner_id' => $salesA->id, 'product_id' => $products[2]->id, 'title' => 'Supply amenities untuk 6 cabang Arunika Suites', 'product_name' => $products[2]->name, 'estimated_quantity' => 12000, 'estimated_value' => 420000000, 'probability' => 65, 'target_price' => 120000, 'offered_price' => 125000, 'expected_close_date' => now()->addDays(28), 'next_action' => 'Review quotation bersama procurement', 'next_follow_up_at' => now()->addDay(), 'priority' => 'high', 'stage_entered_at' => now()->subDays(4)]),
            Opportunity::create(['customer_id' => $customers[1]->id, 'pipeline_id' => $horeka->id, 'pipeline_stage_id' => $horekaStages[2]->id, 'owner_id' => $salesB->id, 'product_id' => $products[0]->id, 'title' => 'Packaging baru untuk layanan delivery Dapur Rasa', 'product_name' => $products[0]->name, 'estimated_quantity' => 5000, 'estimated_value' => 95000000, 'probability' => 40, 'expected_close_date' => now()->addDays(18), 'next_action' => 'Kirim sample material', 'next_follow_up_at' => now()->addDays(2), 'priority' => 'medium', 'stage_entered_at' => now()->subDays(7)]),
            Opportunity::create(['customer_id' => $customers[2]->id, 'pipeline_id' => $project->id, 'pipeline_stage_id' => $projectStages[1]->id, 'owner_id' => $salesA->id, 'product_id' => $products[1]->id, 'title' => 'Booth promosi untuk festival tahunan', 'product_name' => $products[1]->name, 'estimated_quantity' => 1, 'estimated_value' => 175000000, 'probability' => 25, 'expected_close_date' => now()->addDays(45), 'next_action' => 'Finalisasi design brief', 'next_follow_up_at' => now()->addDays(3), 'priority' => 'high', 'stage_entered_at' => now()->subDays(3)]),
        ]);
        foreach ($opportunities as $opportunity) {
            $quantity = max(1, (int) $opportunity->estimated_quantity);
            $opportunity->items()->create([
                'product_id' => $opportunity->product_id,
                'product_name' => $opportunity->product_name,
                'quantity' => $quantity,
                'quantity_unit' => $opportunity->quantity_unit ?: 'pcs',
                'target_price' => $opportunity->target_price,
                'unit_price' => $opportunity->offered_price ?: ((float) $opportunity->estimated_value / $quantity),
                'subtotal' => $opportunity->estimated_value,
            ]);
        }

        foreach ($customers as $i => $customer) {
            Activity::create(['customer_id' => $customer->id, 'opportunity_id' => $opportunities[$i]->id, 'user_id' => $customer->sales_owner_id, 'type' => ['quotation_sent', 'meeting', 'visit'][$i], 'summary' => ['Quotation amenities sudah dikirim', 'Meeting kebutuhan packaging delivery', 'Visit dan pengumpulan design brief'][$i], 'detail' => 'Diskusi berjalan baik dan customer memberikan respons konstruktif.', 'result' => 'Customer setuju melanjutkan ke tahap berikutnya.', 'next_action' => $opportunities[$i]->next_action, 'occurred_at' => now()->subDays($i), 'next_follow_up_at' => $opportunities[$i]->next_follow_up_at]);
        }

        $task = Task::create(['title' => 'Review costing dan supplier amenities', 'description' => 'Validasi harga supplier sebelum quotation final.', 'customer_id' => $customers[0]->id, 'opportunity_id' => $opportunities[0]->id, 'created_by' => $salesA->id, 'reviewer_id' => $manager->id, 'due_at' => now()->addDay(), 'priority' => 'high']);
        $task->assignees()->attach([$purchasing->id, $finance->id]);
        $task2 = Task::create(['title' => 'Siapkan sample packaging', 'customer_id' => $customers[1]->id, 'opportunity_id' => $opportunities[1]->id, 'created_by' => $salesB->id, 'due_at' => now()->subDay(), 'priority' => 'high', 'status' => 'in_progress']);
        $task2->assignees()->attach($warehouse);

        $reportTasks = [
            ['title' => 'Buat detail lead dari laporan', 'description' => 'Tambahkan aksi klik pada jumlah Lead agar pengguna dapat membuka daftar lead yang membentuk angka laporan.', 'priority' => 'high', 'days' => 2, 'assignees' => [$admin->id]],
            ['title' => 'Tampilkan detail lead yang menjadi customer', 'description' => 'Saat kartu Menjadi customer dipilih, tampilkan daftar lead, tanggal konversi, sales, dan customer hasil konversinya.', 'priority' => 'high', 'days' => 3, 'assignees' => [$admin->id, $manager->id]],
            ['title' => 'Tampilkan customer dan produk yang sudah deal', 'description' => 'Tambahkan detail customer, produk deal, jumlah, nilai deal, dan sales pada kartu Customer sudah deal.', 'priority' => 'high', 'days' => 4, 'assignees' => [$admin->id]],
            ['title' => 'Tambahkan export laporan', 'description' => 'Sediakan export Excel dan PDF berdasarkan filter laporan yang sedang digunakan.', 'priority' => 'medium', 'days' => 6, 'assignees' => [$admin->id, $finance->id]],
            ['title' => 'Tambahkan perbandingan periode laporan', 'description' => 'Tampilkan perbandingan periode aktif dengan periode sebelumnya untuk lead, customer, dan deal.', 'priority' => 'medium', 'days' => 7, 'assignees' => [$admin->id, $manager->id]],
            ['title' => 'Simpan filter laporan untuk pengguna', 'description' => 'Tambahkan kemampuan menyimpan filter laporan agar manager dan CSA dapat menggunakannya kembali.', 'priority' => 'medium', 'days' => 8, 'assignees' => [$admin->id]],
        ];

        foreach ($reportTasks as $reportTask) {
            $task = Task::create([
                'title' => $reportTask['title'],
                'description' => $reportTask['description'],
                'created_by' => $admin->id,
                'reviewer_id' => $manager->id,
                'due_at' => now()->addDays($reportTask['days']),
                'priority' => $reportTask['priority'],
            ]);
            $task->assignees()->attach($reportTask['assignees']);
        }

        $approvalActivity = Activity::create([
            'customer_id' => $customers[0]->id,
            'opportunity_id' => $opportunities[0]->id,
            'user_id' => $salesA->id,
            'type' => 'approval_special_price',
            'summary' => 'Pengajuan harga khusus amenities Arunika Suites',
            'detail' => 'Pengajuan untuk volume besar dan potensi ekspansi ke enam cabang.',
            'occurred_at' => now()->subHour(),
            'participants' => [$manager->id],
        ]);
        $approvalActivity->approvalDetail()->create([
            'product_name' => $products[2]->name,
            'normal_price' => 135000,
            'requested_price' => 125000,
            'quantity' => 12000,
            'unit' => 'set',
            'reason' => 'Volume 12.000 set dan potensi ekspansi ke enam cabang.',
            'special_price_items' => ($seedItem = $opportunities[0]->items()->first()) ? [[
                'opportunity_item_id' => $seedItem->id,
                'product_name' => $seedItem->product_name,
                'quantity' => (int) $seedItem->quantity,
                'unit' => $seedItem->quantity_unit,
                'normal_price' => (float) $seedItem->unit_price,
                'target_price' => (float) ($seedItem->target_price ?? 0),
                'requested_price' => 125000,
                'reason' => 'Volume besar dan potensi ekspansi ke enam cabang.',
                'status' => 'pending',
                'decision_note' => null,
            ]] : null,
            'approval_status' => 'pending',
        ]);

        CrmNotification::create(['id' => (string) Str::uuid(), 'user_id' => $manager->id, 'type' => 'approval_waiting', 'title' => 'Approval menunggu keputusan', 'message' => $approvalActivity->summary, 'url' => '/approvals']);
        CrmNotification::create(['id' => (string) Str::uuid(), 'user_id' => $purchasing->id, 'type' => 'task_assigned', 'title' => 'Task baru', 'message' => $task->title, 'url' => '/tasks']);
    }

    private function stages(Pipeline $pipeline, array $rows): array
    {
        return collect($rows)->map(function ($row, $i) use ($pipeline) {
            [$name, $probability, $color, $won, $lost] = array_pad($row, 5, false);
            return $pipeline->stages()->create(['name' => $name, 'slug' => Str::slug($name), 'position' => $i + 1, 'color' => $color, 'probability' => $probability, 'sla_days' => in_array($name, ['Quotation', 'Negotiation']) ? 7 : 14, 'is_won' => $won, 'is_lost' => $lost]);
        })->all();
    }
}
