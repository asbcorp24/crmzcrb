<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('code', 50)->unique();
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->string('icon_path')->nullable();
            $table->string('primary_color', 20)->default('#0d6efd');
            $table->string('secondary_color', 20)->default('#6c757d');
            $table->string('timezone', 64)->default('Europe/Moscow');
            $table->boolean('is_active')->default(true)->index();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        $defaultOrgId = DB::table('organizations')->insertGetId([
            'name' => env('APP_ORGANIZATION_NAME', 'Основная организация'),
            'short_name' => env('APP_ORGANIZATION_SHORT_NAME', 'Организация'),
            'code' => env('APP_ORGANIZATION_CODE', 'main'),
            'slug' => Str::slug(env('APP_ORGANIZATION_NAME', 'Основная организация')) ?: 'main',
            'primary_color' => '#0d6efd',
            'secondary_color' => '#6c757d',
            'timezone' => 'Europe/Moscow',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
            $table->boolean('is_superadmin')->default(false)->after('role')->index();
        });
        DB::table('users')->update(['organization_id' => $defaultOrgId]);
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->unique(['organization_id','email'], 'users_organization_email_unique');
            $table->index(['organization_id','role','is_active'], 'users_org_role_active_idx');
        });

        $tables = ['departments','plans','tasks','positions','staffing_positions','meetings','task_templates'];
        foreach ($tables as $name) {
            if (!Schema::hasTable($name) || Schema::hasColumn($name, 'organization_id')) continue;
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete()->index();
            });
            DB::table($name)->update(['organization_id' => $defaultOrgId]);
        }
    }

    public function down(): void
    {
        foreach (['task_templates','meetings','staffing_positions','positions','tasks','plans','departments'] as $name) {
            if (Schema::hasTable($name) && Schema::hasColumn($name, 'organization_id')) {
                Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('organization_id'));
            }
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_organization_email_unique');
            $table->dropIndex('users_org_role_active_idx');
            $table->dropColumn('is_superadmin');
            $table->dropConstrainedForeignId('organization_id');
            $table->unique('email');
        });
        Schema::dropIfExists('organizations');
    }
};
