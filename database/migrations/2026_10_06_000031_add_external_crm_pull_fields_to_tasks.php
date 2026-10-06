<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('external_crm_remote_status', 32)->nullable()->after('external_crm_sync_status');
            $table->dateTime('external_crm_pulled_at')->nullable()->after('external_crm_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['external_crm_remote_status','external_crm_pulled_at']);
        });
    }
};
