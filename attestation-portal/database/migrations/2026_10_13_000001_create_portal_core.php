<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshops', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->nullable()->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('login')->unique();
            $table->string('password');
            $table->text('password_encrypted')->nullable();
            $table->string('role', 30)->default('employee')->index();
            $table->string('position')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->string('kind', 30)->default('test')->index();
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('is_anonymous')->default(false);
            $table->unsignedInteger('time_limit_minutes')->nullable();
            $table->unsignedInteger('attempts_limit')->default(1);
            $table->decimal('pass_percent', 5, 2)->nullable();
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
            $table->timestamps();
        });

        Schema::create('test_workshop', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['test_id','workshop_id']);
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained()->cascadeOnDelete();
            $table->string('section')->nullable();
            $table->text('text');
            $table->string('type', 30)->index();
            $table->boolean('required')->default(true);
            $table->decimal('weight', 8, 2)->default(1);
            $table->integer('sort_order')->default(0);
            $table->integer('scale_min')->nullable();
            $table->integer('scale_max')->nullable();
            $table->boolean('peer_field')->default(false);
            $table->timestamps();
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->text('label');
            $table->boolean('is_correct')->default(false);
            $table->decimal('score', 8, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workshop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('in_progress')->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('submitted_at')->nullable()->index();
            $table->decimal('score', 10, 2)->default(0);
            $table->decimal('max_score', 10, 2)->default(0);
            $table->decimal('percent', 5, 2)->default(0);
            $table->boolean('passed')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->text('peer_text')->nullable();
            $table->decimal('score', 10, 2)->default(0);
            $table->timestamps();
            $table->unique(['attempt_id','question_id']);
        });

        Schema::create('attestation_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('attestation_campaign_workshop', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('attestation_campaigns')->cascadeOnDelete();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['campaign_id','workshop_id']);
        });

        Schema::create('attestation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('attestation_campaigns')->cascadeOnDelete();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('evaluatee_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->timestamps();
            $table->unique(['campaign_id','evaluator_id','evaluatee_id']);
        });

        Schema::create('attestation_commission_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('attestation_campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['campaign_id','user_id']);
        });

        Schema::create('attestation_commission_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('attestation_campaigns')->cascadeOnDelete();
            $table->foreignId('commission_member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('evaluatee_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id','commission_member_id','evaluatee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attestation_commission_scores');
        Schema::dropIfExists('attestation_commission_members');
        Schema::dropIfExists('attestation_scores');
        Schema::dropIfExists('attestation_campaign_workshop');
        Schema::dropIfExists('attestation_campaigns');
        Schema::dropIfExists('answers');
        Schema::dropIfExists('attempts');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('test_workshop');
        Schema::dropIfExists('tests');
        Schema::dropIfExists('users');
        Schema::dropIfExists('workshops');
    }
};
