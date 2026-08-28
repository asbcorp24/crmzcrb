<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    protected $signature = 'crm:superadmin {email?}';
    protected $description = 'Create or update the global CRM superadmin';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email') ?: $this->ask('Email суперадмина')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $this->error('Некорректный email.'); return self::FAILURE; }
        $password = $this->secret('Пароль суперадмина (минимум 12 символов)');
        if (strlen((string)$password) < 12) { $this->error('Пароль должен содержать минимум 12 символов.'); return self::FAILURE; }

        $user = User::withoutGlobalScopes()->whereNull('organization_id')->where('email',$email)->first() ?: new User();
        $user->fill([
            'organization_id'=>null,'department_id'=>null,'manager_id'=>null,
            'last_name'=>'Суперадмин','first_name'=>'CRM','middle_name'=>null,
            'position'=>'Суперадминистратор','email'=>$email,'role'=>'admin','is_superadmin'=>true,
            'is_active'=>true,'password'=>Hash::make($password),
        ]);
        $user->save();
        $this->info('Суперадмин создан/обновлён: '.$email);
        $this->line('На странице входа оставьте код организации пустым.');
        return self::SUCCESS;
    }
}
