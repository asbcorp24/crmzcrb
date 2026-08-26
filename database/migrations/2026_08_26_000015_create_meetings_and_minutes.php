<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->dateTime('held_at');
            $table->string('location')->nullable();
            $table->foreignId('chairman_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('secretary_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft','active','closed'])->default('draft');
            $table->timestamps();
            $table->index(['held_at','status']);
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['meeting_id','user_id']);
        });

        Schema::create('meeting_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number')->default(1);
            $table->text('instruction');
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->enum('priority', ['low','normal','high','critical'])->default('normal');
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['meeting_id','assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_items');
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
    }
};
