<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->string('type', 40)->index();
            $table->string('code', 80)->nullable();
            $table->string('name', 255);
            $table->string('system_key', 80)->nullable();
            $table->string('color', 20)->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'type', 'code'], 'reference_items_org_type_code_unique');
        });

        if (Schema::hasTable('organizations')) {
            $statuses = [
                ['new', 'Новая', '#0d6efd', 10],
                ['in_progress', 'В работе', '#0dcaf0', 20],
                ['review', 'На проверке', '#ffc107', 30],
                ['completed', 'Выполнена', '#198754', 40],
                ['cancelled', 'Отменена', '#6c757d', 50],
            ];
            foreach (DB::table('organizations')->pluck('id') as $organizationId) {
                foreach ($statuses as [$key, $name, $color, $sort]) {
                    DB::table('reference_items')->insert([
                        'organization_id' => $organizationId,
                        'type' => 'task_status',
                        'code' => strtoupper($key),
                        'name' => $name,
                        'system_key' => $key,
                        'color' => $color,
                        'sort_order' => $sort,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_items');
    }
};
