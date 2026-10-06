<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_meeting_items', function (Blueprint $table) {
            $table->boolean('task_created_from_meeting')->default(false)->after('task_id');
        });

        // До появления выбора существующих задач все task_id создавались самим протоколом.
        DB::table('production_meeting_items')
            ->whereNotNull('task_id')
            ->update(['task_created_from_meeting' => true]);
    }

    public function down(): void
    {
        Schema::table('production_meeting_items', function (Blueprint $table) {
            $table->dropColumn('task_created_from_meeting');
        });
    }
};
