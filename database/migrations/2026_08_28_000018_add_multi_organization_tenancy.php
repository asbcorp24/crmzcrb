<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $safeTable = str_replace("'", "''", $table);
            foreach (DB::select("PRAGMA index_list('{$safeTable}')") as $row) {
                if (($row->name ?? null) === $index) return true;
            }
            return false;
        }

        if ($driver === 'mysql') {
            return !empty(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]));
        }

        if ($driver === 'pgsql') {
            return DB::table('pg_indexes')->where('tablename', $table)->where('indexname', $index)->exists();
        }

        return false;
    }

    public function up(): void
    {
        // Миграция возобновляемая и совместима с MySQL/MariaDB и SQLite.
        if (!Schema::hasTable('organizations')) {
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
        }

        $defaultOrgId = (int) DB::table('organizations')->where('code', env('APP_ORGANIZATION_CODE', 'main'))->value('id');
        if (!$defaultOrgId) {
            $baseSlug = Str::slug(env('APP_ORGANIZATION_NAME', 'Основная организация')) ?: 'main';
            $slug = $baseSlug;
            $n = 2;
            while (DB::table('organizations')->where('slug', $slug)->exists()) $slug = $baseSlug.'-'.$n++;
            $defaultOrgId = (int) DB::table('organizations')->insertGetId([
                'name'=>env('APP_ORGANIZATION_NAME','Основная организация'),
                'short_name'=>env('APP_ORGANIZATION_SHORT_NAME','Организация'),
                'code'=>env('APP_ORGANIZATION_CODE','main'),
                'slug'=>$slug,
                'primary_color'=>'#0d6efd','secondary_color'=>'#6c757d','timezone'=>'Europe/Moscow','is_active'=>true,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }

        if (!Schema::hasColumn('users', 'organization_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            });
        }
        if (!Schema::hasColumn('users', 'is_superadmin')) {
            Schema::table('users', fn (Blueprint $table) => $table->boolean('is_superadmin')->default(false)->after('role'));
        }
        DB::table('users')->whereNull('organization_id')->update(['organization_id'=>$defaultOrgId]);

        if ($this->indexExists('users','users_email_unique')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropUnique('users_email_unique'));
        }
        if (!$this->indexExists('users','users_organization_email_unique')) {
            Schema::table('users', fn (Blueprint $table) => $table->unique(['organization_id','email'],'users_organization_email_unique'));
        }
        if (!$this->indexExists('users','users_org_role_active_idx')) {
            Schema::table('users', fn (Blueprint $table) => $table->index(['organization_id','role','is_active'],'users_org_role_active_idx'));
        }
        if (!$this->indexExists('users','users_is_superadmin_index')) {
            Schema::table('users', fn (Blueprint $table) => $table->index('is_superadmin','users_is_superadmin_index'));
        }

        $tables=['departments','plans','tasks','task_comments','task_events','crm_notifications','task_attachments','positions','staffing_positions','employee_assignments','task_templates','task_template_checklist_items','task_checklist_items','task_deadline_changes','task_overdue_reasons','employee_absences','employee_substitutions','task_delegations','meetings','meeting_items','entity_comments','entity_attachments','push_subscriptions','task_tags'];
        foreach ($tables as $name) {
            if (!Schema::hasTable($name)) continue;
            if (!Schema::hasColumn($name,'organization_id')) {
                Schema::table($name, fn (Blueprint $table) => $table->unsignedBigInteger('organization_id')->nullable()->after('id'));
            }
            DB::table($name)->whereNull('organization_id')->update(['organization_id'=>$defaultOrgId]);
            $indexName = 'idx_'.$name.'_organization';
            if (!$this->indexExists($name,$indexName)) {
                Schema::table($name, fn (Blueprint $table) => $table->index('organization_id',$indexName));
            }
        }

        if (Schema::hasTable('positions')) {
            if ($this->indexExists('positions','positions_code_unique')) {
                Schema::table('positions',fn(Blueprint $table)=>$table->dropUnique('positions_code_unique'));
            }
            if (!$this->indexExists('positions','positions_org_code_unique')) {
                Schema::table('positions',fn(Blueprint $table)=>$table->unique(['organization_id','code'],'positions_org_code_unique'));
            }
        }

        if (Schema::hasTable('task_tags')) {
            if ($this->indexExists('task_tags','task_tags_name_unique')) Schema::table('task_tags',fn(Blueprint $table)=>$table->dropUnique('task_tags_name_unique'));
            if ($this->indexExists('task_tags','task_tags_slug_unique')) Schema::table('task_tags',fn(Blueprint $table)=>$table->dropUnique('task_tags_slug_unique'));
            if (!$this->indexExists('task_tags','task_tags_org_name_unique')) Schema::table('task_tags',fn(Blueprint $table)=>$table->unique(['organization_id','name'],'task_tags_org_name_unique'));
            if (!$this->indexExists('task_tags','task_tags_org_slug_unique')) Schema::table('task_tags',fn(Blueprint $table)=>$table->unique(['organization_id','slug'],'task_tags_org_slug_unique'));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('task_tags')) {
            if ($this->indexExists('task_tags','task_tags_org_name_unique')) Schema::table('task_tags',fn(Blueprint $table)=>$table->dropUnique('task_tags_org_name_unique'));
            if ($this->indexExists('task_tags','task_tags_org_slug_unique')) Schema::table('task_tags',fn(Blueprint $table)=>$table->dropUnique('task_tags_org_slug_unique'));
        }
        if (Schema::hasTable('positions') && $this->indexExists('positions','positions_org_code_unique')) {
            Schema::table('positions',fn(Blueprint $table)=>$table->dropUnique('positions_org_code_unique'));
        }

        $tables=['task_tags','push_subscriptions','entity_attachments','entity_comments','meeting_items','meetings','task_delegations','employee_substitutions','employee_absences','task_overdue_reasons','task_deadline_changes','task_checklist_items','task_template_checklist_items','task_templates','employee_assignments','staffing_positions','positions','task_attachments','crm_notifications','task_events','task_comments','tasks','plans','departments'];
        foreach($tables as $name){
            if(!Schema::hasTable($name)||!Schema::hasColumn($name,'organization_id'))continue;
            $idx='idx_'.$name.'_organization';
            if($this->indexExists($name,$idx))Schema::table($name,fn(Blueprint $table)=>$table->dropIndex($idx));
            Schema::table($name,fn(Blueprint $table)=>$table->dropColumn('organization_id'));
        }

        if (Schema::hasTable('users')) {
            if ($this->indexExists('users','users_organization_email_unique')) Schema::table('users',fn(Blueprint $table)=>$table->dropUnique('users_organization_email_unique'));
            if ($this->indexExists('users','users_org_role_active_idx')) Schema::table('users',fn(Blueprint $table)=>$table->dropIndex('users_org_role_active_idx'));
            if ($this->indexExists('users','users_is_superadmin_index')) Schema::table('users',fn(Blueprint $table)=>$table->dropIndex('users_is_superadmin_index'));
            Schema::table('users', function(Blueprint $table){
                if(Schema::hasColumn('users','is_superadmin'))$table->dropColumn('is_superadmin');
                if(Schema::hasColumn('users','organization_id'))$table->dropColumn('organization_id');
            });
            if (!$this->indexExists('users','users_email_unique')) Schema::table('users',fn(Blueprint $table)=>$table->unique('email'));
        }
        Schema::dropIfExists('organizations');
    }
};
