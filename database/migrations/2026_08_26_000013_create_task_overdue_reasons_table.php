<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_overdue_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('reason_code', ['waiting_data','technical','dependency','workload','other']);
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->index(['task_id','created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_overdue_reasons');
    }
};
