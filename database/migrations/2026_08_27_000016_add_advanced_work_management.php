<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('parent_task_id')->nullable()->after('plan_id')->constrained('tasks')->nullOnDelete();
            $table->dateTime('archived_at')->nullable()->after('completed_at')->index();
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->dateTime('archived_at')->nullable()->index();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('meetings', function (Blueprint $table) {
            $table->dateTime('archived_at')->nullable()->index();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('archived_at')->nullable()->index();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('blocked_by_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['task_id','blocked_by_task_id']);
        });

        Schema::create('task_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name',100)->unique();
            $table->string('slug',120)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('task_tag', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('task_tag_id')->constrained('task_tags')->cascadeOnDelete();
            $table->primary(['task_id','task_tag_id']);
        });
        $now=now();
        DB::table('task_tags')->insert([
            ['name'=>'ОМС','slug'=>'oms','is_active'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['name'=>'ТФОМС','slug'=>'tfoms','is_active'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['name'=>'ИТ','slug'=>'it','is_active'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['name'=>'Кадры','slug'=>'kadry','is_active'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['name'=>'Приказ','slug'=>'prikaz','is_active'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['name'=>'Срочно','slug'=>'srochno','is_active'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['name'=>'Отчётность','slug'=>'otchetnost','is_active'=>1,'created_at'=>$now,'updated_at'=>$now],
        ]);

        Schema::create('entity_comments', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type',32);
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['entity_type','entity_id','created_at']);
        });
        Schema::create('entity_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type',32);
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
            $table->index(['entity_type','entity_id','created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_attachments');
        Schema::dropIfExists('entity_comments');
        Schema::dropIfExists('task_tag');
        Schema::dropIfExists('task_tags');
        Schema::dropIfExists('task_dependencies');
        Schema::table('tasks', fn (Blueprint $table) => $table->dropConstrainedForeignId('parent_task_id'));
        foreach (['tasks','plans','meetings','users'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('archived_by');
                $table->dropColumn('archived_at');
            });
        }
    }
};
