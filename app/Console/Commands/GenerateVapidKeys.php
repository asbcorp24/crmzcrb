<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateVapidKeys extends Command
{
    protected $signature = 'crm:vapid-keys';
    protected $description = 'Генерирует VAPID-ключи для PWA Web Push';

    public function handle(): int
    {
        if (!class_exists(\Minishlink\WebPush\VAPID::class)) {
            $this->error('Пакет minishlink/web-push не установлен. Выполните composer update minishlink/web-push.');
            return self::FAILURE;
        }

        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
        $subject = config('app.url') ?: 'https://localhost';

        $this->newLine();
        $this->info('Добавьте эти строки в .env:');
        $this->line('VAPID_SUBJECT='.$subject);
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->warn('PRIVATE_KEY не публикуйте и не добавляйте в Git. После изменения .env выполните php artisan optimize:clear.');

        return self::SUCCESS;
    }
}
