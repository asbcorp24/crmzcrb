<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->after('plan_id')->index();
            $table->unsignedBigInteger('basis_id')->nullable()->after('project_id')->index();
            $table->unsignedBigInteger('responsible_department_id')->nullable()->after('basis_id')->index();
            $table->dateTime('start_at')->nullable()->after('responsible_department_id');
            $table->string('customer_type', 30)->nullable()->after('start_at');
            $table->unsignedBigInteger('customer_id')->nullable()->after('customer_type')->index();
            $table->unsignedBigInteger('business_status_id')->nullable()->after('customer_id')->index();
        });

        Schema::create('task_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('url');
            $table->timestamps();
            $table->index(['task_id','created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_links');
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'project_id','basis_id','responsible_department_id','start_at',
                'customer_type','customer_id','business_status_id',
            ]);
        });
    }
};
