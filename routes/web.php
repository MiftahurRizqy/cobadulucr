<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerDuplicateController;
use App\Http\Controllers\CustomerTypeSettingController;
use App\Http\Controllers\CompanySelectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentActivityPolicyController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\KpiController;
use App\Http\Controllers\KpiMetricSettingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OperationalSettingController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ValidationSettingController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::get('/choose-company', [CompanySelectionController::class, 'create'])->name('company.select');
Route::post('/choose-company', [CompanySelectionController::class, 'store'])->name('company.select.store');
Route::post('/sign-out', [CompanySelectionController::class, 'destroy'])->name('company.logout');

Route::middleware(['tenant', 'auth', 'active'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::get('/customer-duplicate-check', CustomerDuplicateController::class)->name('customers.duplicate-check');
    Route::post('/presence/heartbeat', [PresenceController::class, 'heartbeat'])->name('presence.heartbeat');
    Route::get('/users/active', [PresenceController::class, 'index'])->name('users.active');
    Route::get('/users/active/data', [PresenceController::class, 'data'])->name('users.active.data');

    Route::middleware('permission:leads.view')->group(function () {
        Route::resource('leads', LeadController::class)->except(['show', 'destroy']);
        Route::post('/leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
    });

    Route::middleware('permission:customers.view')->group(function () {
        Route::resource('customers', CustomerController::class)->only(['index', 'show']);
        Route::post('/customers/{customer}/documents', [CustomerController::class, 'storeDocument'])->name('customers.documents.store');
    });
    Route::middleware('permission:customers.edit')->group(function () {
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::patch('/customers/{customer}', [CustomerController::class, 'update']);
    });

    Route::middleware('permission:opportunities.view')->group(function () {
        Route::get('/opportunities/kanban', [OpportunityController::class, 'kanban'])->name('opportunities.kanban');
        Route::get('/opportunities/custom-progress', [OpportunityController::class, 'customProgress'])->name('opportunities.custom-progress');
        Route::resource('opportunities', OpportunityController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('/opportunities/{opportunity}/stage', [OpportunityController::class, 'moveStage'])->name('opportunities.stage');
        Route::patch('/opportunities/{opportunity}/general-info', [OpportunityController::class, 'updateGeneralInfo'])->name('opportunities.general-info');
        Route::patch('/opportunities/{opportunity}/quotation', [OpportunityController::class, 'updateQuotation'])->name('opportunities.quotation');
        Route::post('/opportunities/{opportunity}/items', [OpportunityController::class, 'storeItem'])->name('opportunities.items.store');
        Route::patch('/opportunities/{opportunity}/items/{item}/price', [OpportunityController::class, 'updateItemPrice'])->name('opportunities.items.price');
        Route::patch('/opportunities/{opportunity}/items/{item}', [OpportunityController::class, 'updateItem'])->name('opportunities.items.update');
        Route::patch('/opportunities/{opportunity}/items/{item}/status', [OpportunityController::class, 'updateItemStatus'])->name('opportunities.items.status');
        Route::patch('/opportunities/{opportunity}/items/{item}/custom-stage', [OpportunityController::class, 'updateItemCustomStage'])->name('opportunities.items.custom-stage');
        Route::patch('/opportunities/{opportunity}/items/{item}/custom-stage', [OpportunityController::class, 'updateItemCustomStage'])->name('opportunities.items.custom-stage');
        Route::patch('/opportunities/{opportunity}/items/{item}/custom-stage', [OpportunityController::class, 'updateItemCustomStage'])->name('opportunities.items.custom-stage');
    });

    Route::middleware('permission:activities.view')->group(function () {
        Route::resource('activities', ActivityController::class)->only(['index', 'create', 'store']);
        Route::get('/activity-follow-ups/pending', [ActivityController::class, 'pendingFollowUps'])->name('activities.follow-ups.pending');
        Route::get('/activities/{activity}/follow-up', [ActivityController::class, 'followUp'])->name('activities.follow-up');
        Route::post('/activities/{activity}/follow-up/complete', [ActivityController::class, 'completeFollowUp'])->name('activities.follow-up.complete');
        Route::get('/activities/{activity}/comments', [ActivityController::class, 'comments'])->name('activities.comments');
        Route::post('/activities/{activity}/comments', [ActivityController::class, 'comment'])->name('activities.comments.store');
        Route::post('/activities/{activity}/participants', [ActivityController::class, 'addParticipants'])->name('activities.participants.store');
    });

    Route::middleware('permission:tasks.view')->group(function () {
        Route::resource('tasks', TaskController::class)->only(['index', 'create', 'store']);
        Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
    });

    Route::middleware('permission:approvals.view')->group(function () {
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::get('/approvals/{activity}/captcha', [ApprovalController::class, 'captcha'])->name('approvals.captcha');
        Route::get('/approvals/{activity}/revise', [ApprovalController::class, 'revise'])->name('approvals.revise');
        Route::patch('/approvals/{activity}/resubmit', [ApprovalController::class, 'resubmit'])->name('approvals.resubmit');
        Route::post('/activities/{activity}/approval-decision', [ActivityController::class, 'decideApproval'])->name('activities.approval.decision');
    });

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/poll', [NotificationController::class, 'poll'])->name('notifications.poll');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('/reports', ReportController::class)->middleware('permission:reports.view')->name('reports.index');
    Route::get('/reports/export.csv', [ReportController::class, 'exportCsv'])->middleware('permission:reports.view')->name('reports.export.csv');
    Route::get('/reports/export.xlsx', [ReportController::class, 'exportExcel'])->middleware('permission:reports.view')->name('reports.export.excel');
    Route::get('/reports/export.pdf', [ReportController::class, 'exportPdf'])->middleware('permission:reports.view')->name('reports.export.pdf');
    Route::middleware('permission:kpi.view')->group(function () {
        Route::get('/kpi', [KpiController::class, 'index'])->name('kpi.index');
        Route::get('/kpi/export/excel', [KpiController::class, 'exportExcel'])->name('kpi.export.excel');
        Route::get('/kpi/export/pdf', [KpiController::class, 'exportPdf'])->name('kpi.export.pdf');
    });
    Route::put('/kpi/{sales}', [KpiController::class, 'update'])->middleware('permission:kpi.manage')->name('kpi.update');
    Route::middleware('permission:kpi.manage')->group(function () {
        Route::put('/kpi-templates/{roleSlug}', [KpiController::class, 'updateTemplate'])->name('kpi.templates.update');
        Route::post('/kpi-templates/{roleSlug}/apply', [KpiController::class, 'applyTemplate'])->name('kpi.templates.apply');
        Route::post('/kpi-targets/copy-previous', [KpiController::class, 'copyPrevious'])->name('kpi.targets.copy-previous');
    });

    Route::middleware('permission:admin.manage')->group(function () {
        Route::get('/platform/companies', [TenantController::class, 'index'])->name('tenants.index');
        Route::post('/platform/companies', [TenantController::class, 'store'])->name('tenants.store');
        Route::patch('/platform/companies/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
        Route::patch('/platform/companies/{tenant}/complete-setup', [TenantController::class, 'completeSetup'])->name('tenants.complete-setup');
        Route::patch('/platform/companies/{tenant}/toggle', [TenantController::class, 'toggle'])->name('tenants.toggle');
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::resource('areas', AreaController::class)->except(['show', 'destroy']);
        Route::post('/roles/apply-templates', [RoleController::class, 'applyTemplates'])->name('roles.apply-templates');
        Route::resource('roles', RoleController::class)->except('show');
        Route::resource('pipelines', PipelineController::class)->except(['show', 'destroy']);
        Route::get('/settings/activity-evidence', [DepartmentActivityPolicyController::class, 'index'])->name('settings.activity-evidence.index');
        Route::put('/settings/activity-evidence', [DepartmentActivityPolicyController::class, 'update'])->name('settings.activity-evidence.update');
        Route::get('/settings/validation', [ValidationSettingController::class, 'index'])->name('settings.validation.index');
        Route::put('/settings/validation', [ValidationSettingController::class, 'update'])->name('settings.validation.update');
        Route::get('/settings/operational', [OperationalSettingController::class, 'index'])->name('settings.operational.index');
        Route::put('/settings/operational', [OperationalSettingController::class, 'update'])->name('settings.operational.update');
        Route::get('/settings/kpi-metrics', [KpiMetricSettingController::class, 'index'])->name('settings.kpi-metrics.index');
        Route::put('/settings/kpi-metrics', [KpiMetricSettingController::class, 'update'])->name('settings.kpi-metrics.update');
        Route::get('/settings/customer-types', [CustomerTypeSettingController::class, 'index'])->name('settings.customer-types.index');
        Route::post('/settings/customer-types', [CustomerTypeSettingController::class, 'store'])->name('settings.customer-types.store');
        Route::put('/settings/customer-types/{customerType}', [CustomerTypeSettingController::class, 'update'])->name('settings.customer-types.update');
        Route::patch('/settings/customer-types/{customerType}/toggle', [CustomerTypeSettingController::class, 'toggle'])->name('settings.customer-types.toggle');
        Route::delete('/settings/customer-types/{customerType}', [CustomerTypeSettingController::class, 'destroy'])->name('settings.customer-types.destroy');
        Route::get('/audit-log', AuditLogController::class)->name('audit.index');
    });
});
