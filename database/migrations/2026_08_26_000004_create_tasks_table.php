<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['low','normal','high','critical'])->default('normal');
            $table->enum('status', ['new','in_progress','review','completed','cancelled'])->default('new');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('result')->nullable();
            $table->timestamps();
            $table->index(['assigned_to','status','due_at']);
            $table->index(['created_by','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('tasks'); }
};
