<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['vacation','sick_leave','business_trip','training','other']);
            $table->date('date_from');
            $table->date('date_to');
            $table->string('document_number')->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['user_id','date_from','date_to']);
        });

        Schema::create('employee_substitutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absent_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('substitute_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->text('comment')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['absent_user_id','date_from','date_to']);
        });

        Schema::create('task_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegated_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->timestamps();
            $table->index(['task_id','created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_delegations');
        Schema::dropIfExists('employee_substitutions');
        Schema::dropIfExists('employee_absences');
    }
};
