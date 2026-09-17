<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('production_meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->string('title');
            $table->string('protocol_number')->nullable();
            $table->dateTime('held_at')->nullable();
            $table->unsignedBigInteger('chairman_id')->nullable()->index();
            $table->unsignedBigInteger('secretary_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->index();
            $table->string('status', 30)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->dateTime('protocol_generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('production_meeting_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_meeting_id')->index();
            $table->unsignedInteger('number')->default(1);
            $table->text('instruction');
            $table->unsignedBigInteger('responsible_department_id')->nullable()->index();
            $table->unsignedBigInteger('coexecutor_id')->nullable()->index();
            $table->date('start_at')->nullable();
            $table->date('due_at')->nullable();
            $table->unsignedInteger('duration_days')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedBigInteger('task_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->index();
            $table->timestamps();

            $table->unique(['production_meeting_id', 'number'], 'prod_meeting_item_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_meeting_items');
        Schema::dropIfExists('production_meetings');
    }
};
