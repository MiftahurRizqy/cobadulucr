<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_units', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_frontliner')->default(false);
            $table->boolean('activity_evidence_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('branch')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            $table->string('action');
            $table->string('key')->unique();
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('permission_role_denials', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('current_path', 500)->nullable();
            $table->string('current_page', 160)->nullable();
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();
        });

        DB::table('roles')->insert([
            'name' => 'Master Admin',
            'slug' => 'master_admin',
            'description' => 'System role for master administrator',
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            'business_unit_user' => 'business_unit_id',
            'department_user' => 'department_id',
            'team_user' => 'team_id',
            'area_user' => 'area_id',
        ] as $tableName => $foreign) {
            Schema::create($tableName, function (Blueprint $table) use ($foreign) {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger($foreign);
                $table->primary(['user_id', $foreign]);
            });
        }
    }

    public function down(): void
    {
        foreach (['user_presences', 'area_user', 'team_user', 'department_user', 'business_unit_user', 'permission_role_denials', 'permission_role', 'role_user', 'permissions', 'roles', 'areas', 'teams', 'departments', 'business_units'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
