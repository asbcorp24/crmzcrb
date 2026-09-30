<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('external_crm_task_id')->nullable()->after('business_status_id')->index();
            $table->string('external_crm_sync_status', 30)->nullable()->after('external_crm_task_id');
            $table->text('external_crm_sync_error')->nullable()->after('external_crm_sync_status');
            $table->dateTime('external_crm_synced_at')->nullable()->after('external_crm_sync_error');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['external_crm_task_id']);
            $table->dropColumn([
                'external_crm_task_id',
                'external_crm_sync_status',
                'external_crm_sync_error',
                'external_crm_synced_at',
            ]);
        });
    }
};
