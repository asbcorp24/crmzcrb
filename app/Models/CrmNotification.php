<?php

namespace App\Models;

use App\Services\WebPushService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class CrmNotification extends Model
{
    protected $fillable = ['user_id','task_id','type','title','body','url','read_at'];
    protected $casts = ['read_at' => 'datetime'];

    protected static function booted(): void
    {
        static::created(function (CrmNotification $notification) {
            try {
                app(WebPushService::class)->sendNotification($notification);
            } catch (\Throwable $e) {
                Log::warning('CRM push dispatch failed', ['notification_id'=>$notification->id,'message'=>$e->getMessage()]);
            }
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
}
