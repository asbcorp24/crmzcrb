<?php
namespace Database\Seeders;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder { public function run(): void { $administration=Department::firstOrCreate(['name'=>'Администрация'],['short_name'=>'Администрация','type'=>'administration']); User::firstOrCreate(['email'=>'admin@zcrb.local'],['department_id'=>$administration->id,'last_name'=>'Администратор','first_name'=>'Системы','position'=>'Администратор CRM','role'=>'admin','is_active'=>true,'password'=>Hash::make('ChangeMe123!')]); } }
