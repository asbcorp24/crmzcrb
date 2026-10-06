<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('external_crm_recipient_name')->nullable()->after('external_crm_remote_status');
            $table->text('external_crm_last_log')->nullable()->after('external_crm_recipient_name');
            $table->dateTime('external_crm_last_log_at')->nullable()->after('external_crm_last_log');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'external_crm_recipient_name',
                'external_crm_last_log',
                'external_crm_last_log_at',
            ]);
        });
    }
};
