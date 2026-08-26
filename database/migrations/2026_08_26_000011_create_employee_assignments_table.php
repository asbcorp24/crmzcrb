<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staffing_position_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate', 4, 2)->default(1.00);
            $table->boolean('is_primary')->default(false);
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->string('order_number', 100)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['user_id','ended_at','is_primary']);
            $table->index(['staffing_position_id','ended_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('employee_assignments'); }
};
