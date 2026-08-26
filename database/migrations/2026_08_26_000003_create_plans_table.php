<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('period_type', ['day','week','month','quarter','year','custom'])->default('month');
            $table->enum('status', ['draft','active','completed','cancelled'])->default('draft');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamps();
            $table->index(['user_id','period_start','period_end','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('plans'); }
};
