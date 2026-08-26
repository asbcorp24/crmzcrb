<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $administration = Department::firstOrCreate(
            ['name'=>'Администрация'],
            ['short_name'=>'Администрация','type'=>'administration','is_active'=>true,'sort_order'=>10]
        );

        User::firstOrCreate(
            ['email'=>'admin@zcrb.local'],
            [
                'department_id'=>$administration->id,
                'last_name'=>'Администратор',
                'first_name'=>'Системы',
                'position'=>'Администратор CRM',
                'role'=>'admin',
                'is_active'=>true,
                'password'=>Hash::make('ChangeMe123!'),
            ]
        );

        // На production демо-данные не создаются случайно.
        // Запуск: php artisan db:seed --class=DemoSeeder
        // либо SEED_DEMO_DATA=true php artisan db:seed
        if (app()->environment(['local','development','testing']) || filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL)) {
            $this->call(DemoSeeder::class);
        }
    }
}
