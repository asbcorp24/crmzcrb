<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->after('assigned_to')->index();
            $table->unsignedBigInteger('basis_id')->nullable()->after('project_id')->index();
            $table->unsignedBigInteger('responsible_department_id')->nullable()->after('basis_id')->index();
            $table->unsignedInteger('start_after_days')->nullable()->after('responsible_department_id');
            $table->string('customer_type', 30)->nullable()->after('start_after_days');
            $table->unsignedBigInteger('customer_id')->nullable()->after('customer_type')->index();
            $table->unsignedBigInteger('business_status_id')->nullable()->after('customer_id')->index();
            $table->boolean('add_to_plan')->default(false)->after('business_status_id');
        });
    }

    public function down(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
            $table->dropIndex(['basis_id']);
            $table->dropIndex(['responsible_department_id']);
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['business_status_id']);
            $table->dropColumn([
                'project_id','basis_id','responsible_department_id','start_after_days',
                'customer_type','customer_id','business_status_id','add_to_plan'
            ]);
        });
    }
};
