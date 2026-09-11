<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    protected $signature = 'crm:superadmin';
    protected $description = 'Create or update the global CRM superadmin using .env credentials';

    public function handle(): int
    {
        $login = trim((string) env('SUPERADMIN_LOGIN', ''));
        $password = (string) env('SUPERADMIN_PASSWORD', '');

        if ($login === '' || $password === '') {
            $this->error('В .env должны быть заданы SUPERADMIN_LOGIN и SUPERADMIN_PASSWORD.');
            return self::FAILURE;
        }
        if (strlen($password) < 12) {
            $this->error('SUPERADMIN_PASSWORD должен содержать минимум 12 символов.');
            return self::FAILURE;
        }

        $user = User::withoutGlobalScopes()
            ->whereNull('organization_id')
            ->where('is_superadmin', true)
            ->first() ?: new User();

        $dbEmail = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? strtolower($login)
            : 'superadmin@local.invalid';

        $user->fill([
            'organization_id' => null,
            'department_id' => null,
            'manager_id' => null,
            'last_name' => 'Суперадмин',
            'first_name' => 'CRM',
            'middle_name' => null,
            'position' => 'Суперадминистратор',
            'email' => $dbEmail,
            'role' => 'admin',
            'is_superadmin' => true,
            'is_active' => true,
            'password' => Hash::make($password),
        ]);
        $user->save();

        $this->info('Суперадмин создан/обновлён из .env.');
        $this->line('Логин: '.$login);
        $this->line('На странице входа оставьте код организации пустым.');
        return self::SUCCESS;
    }
}
