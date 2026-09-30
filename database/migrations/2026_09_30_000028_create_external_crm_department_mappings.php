<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_crm_department_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->unsignedBigInteger('external_workshop_id');
            $table->string('external_workshop_name')->nullable();
            $table->unsignedBigInteger('external_manager_user_id');
            $table->string('external_manager_name')->nullable();
            $table->unsignedBigInteger('external_task_type_id');
            $table->string('external_task_type_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'department_id'], 'ext_crm_org_department_unique');
            $table->index(['organization_id', 'external_workshop_id'], 'ext_crm_org_workshop_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_crm_department_mappings');
    }
};
