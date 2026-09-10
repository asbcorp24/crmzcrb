<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attestation_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('attestation_campaign_departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id')->index();
            $table->unsignedBigInteger('department_id')->index();
            $table->timestamps();
            $table->unique(['campaign_id','department_id'],'attestation_campaign_department_unique');
        });

        Schema::create('attestation_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('campaign_id')->index();
            $table->unsignedBigInteger('department_id')->index();
            $table->unsignedBigInteger('evaluator_id')->index();
            $table->unsignedBigInteger('evaluatee_id')->index();
            $table->unsignedTinyInteger('score');
            $table->timestamps();
            $table->unique(['campaign_id','evaluator_id','evaluatee_id'],'attestation_score_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attestation_scores');
        Schema::dropIfExists('attestation_campaign_departments');
        Schema::dropIfExists('attestation_campaigns');
    }
};
