<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $login=env('SUPERADMIN_LOGIN','superadmin');
        $password=env('SUPERADMIN_PASSWORD','ChangeMe123!');
        $user=User::firstOrNew(['login'=>$login]);
        $user->full_name=env('SUPERADMIN_FIO','Супер администратор');
        $user->role='superadmin';
        $user->is_active=true;
        $user->setVisiblePassword($password);
        $user->save();
    }
}
