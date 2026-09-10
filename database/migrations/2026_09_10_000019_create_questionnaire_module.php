<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('questionnaires')) {
            Schema::create('questionnaires', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('instructions')->nullable();
                $table->string('status', 20)->default('draft')->index();
                $table->boolean('is_anonymous')->default(false);
                $table->dateTime('opens_at')->nullable();
                $table->dateTime('closes_at')->nullable();
                $table->json('schema');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('questionnaire_departments')) {
            Schema::create('questionnaire_departments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->unsignedBigInteger('questionnaire_id')->index();
                $table->unsignedBigInteger('department_id')->index();
                $table->timestamps();
                $table->unique(['questionnaire_id', 'department_id'], 'questionnaire_department_unique');
            });
        }

        if (!Schema::hasTable('questionnaire_responses')) {
            Schema::create('questionnaire_responses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->unsignedBigInteger('questionnaire_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->string('respondent_name')->nullable();
                $table->string('position')->nullable();
                $table->json('answers');
                $table->dateTime('submitted_at')->nullable()->index();
                $table->timestamps();
                $table->unique(['questionnaire_id', 'user_id'], 'questionnaire_user_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_responses');
        Schema::dropIfExists('questionnaire_departments');
        Schema::dropIfExists('questionnaires');
    }
};
