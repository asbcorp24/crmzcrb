<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('position')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->enum('role', ['admin','manager','employee'])->default('employee');
            $table->boolean('is_active')->default(true);
            $table->date('employment_date')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->index(['department_id','role','is_active']);
        });
    }
    public function down(): void { Schema::dropIfExists('users'); }
};
