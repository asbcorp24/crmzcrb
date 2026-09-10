<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attestation_commission_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('campaign_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamps();
            $table->unique(['campaign_id','user_id'], 'attestation_commission_member_unique');
        });

        Schema::create('attestation_commission_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('campaign_id')->index();
            $table->unsignedBigInteger('commission_member_id')->index();
            $table->unsignedBigInteger('evaluatee_id')->index();
            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id','commission_member_id','evaluatee_id'], 'attestation_commission_score_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attestation_commission_scores');
        Schema::dropIfExists('attestation_commission_members');
    }
};
