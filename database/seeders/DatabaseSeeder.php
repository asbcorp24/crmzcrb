<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::orderBy('id')->firstOrFail();
        config(['tenant.organization_id'=>$organization->id]);

        $administration = Department::firstOrCreate(
            ['organization_id'=>$organization->id,'name'=>'Администрация'],
            ['short_name'=>'Администрация','type'=>'administration','is_active'=>true,'sort_order'=>10]
        );

        User::firstOrCreate(
            ['organization_id'=>$organization->id,'email'=>'admin@zcrb.local'],
            [
                'department_id'=>$administration->id,
                'last_name'=>'Администратор','first_name'=>'Системы','position'=>'Администратор CRM',
                'role'=>'admin','is_superadmin'=>false,'is_active'=>true,'password'=>Hash::make('ChangeMe123!'),
            ]
        );

        if (app()->environment(['local','development','testing']) || filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL)) {
            $this->call(DemoSeeder::class);
        }
    }
}
