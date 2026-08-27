<?php

namespace App\Services;

use App\Models\CrmNotification;
use App\Models\PushSubscription as PushSubscriptionModel;
use Illuminate\Support\Facades\Log;

class WebPushService
{
    public function configured(): bool
    {
        return class_exists(\Minishlink\WebPush\WebPush::class)
            && filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }

    public function sendNotification(CrmNotification $notification): int
    {
        if (!$this->configured()) return 0;

        $subscriptions = PushSubscriptionModel::where('user_id', $notification->user_id)->get();
        if ($subscriptions->isEmpty()) return 0;

        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject' => config('webpush.vapid.subject'),
                'publicKey' => config('webpush.vapid.public_key'),
                'privateKey' => config('webpush.vapid.private_key'),
            ],
        ]);
        $topic='crm-'.substr(hash('sha256',(string)($notification->type ?: 'notification')),0,20);
        $webPush->setDefaultOptions(['TTL'=>86400,'urgency'=>'normal','topic'=>$topic]);

        $payload = json_encode([
            'title' => $notification->title ?: 'CRM ЗЦРБ',
            'body' => $notification->body ?: '',
            'url' => $notification->url ?: '/',
            'notification_id' => $notification->id,
            'type' => $notification->type,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $sent = 0;
        foreach ($subscriptions as $row) {
            try {
                $subscription = \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $row->endpoint,
                    'publicKey' => $row->public_key,
                    'authToken' => $row->auth_token,
                    'contentEncoding' => $row->content_encoding ?: 'aes128gcm',
                ]);
                $report = $webPush->sendOneNotification($subscription, $payload);
                if ($report->isSuccess()) {
                    $row->forceFill(['last_used_at'=>now()])->saveQuietly();
                    $sent++;
                } elseif ($report->isSubscriptionExpired()) {
                    $row->delete();
                } else {
                    Log::warning('Web Push delivery failed', ['endpoint_hash'=>$row->endpoint_hash,'reason'=>$report->getReason()]);
                }
            } catch (\Throwable $e) {
                Log::warning('Web Push exception', ['endpoint_hash'=>$row->endpoint_hash,'message'=>$e->getMessage()]);
            }
        }
        return $sent;
    }
}
