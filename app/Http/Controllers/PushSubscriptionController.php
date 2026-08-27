<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function page(Request $request)
    {
        return view('pwa.settings');
    }

    public function status(Request $request)
    {
        return response()->json([
            'configured' => app(WebPushService::class)->configured(),
            'public_key' => config('webpush.vapid.public_key'),
            'subscriptions' => PushSubscription::where('user_id',$request->user()->id)->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data=$request->validate([
            'endpoint'=>'required|string|max:4000',
            'keys.p256dh'=>'required|string|max:4000',
            'keys.auth'=>'required|string|max:4000',
            'contentEncoding'=>'nullable|string|max:32',
        ]);
        abort_unless(app(WebPushService::class)->configured(),503,'Push-уведомления ещё не настроены на сервере');
        $hash=hash('sha256',$data['endpoint']);
        $subscription=PushSubscription::updateOrCreate(['endpoint_hash'=>$hash],[
            'user_id'=>$request->user()->id,
            'endpoint'=>$data['endpoint'],
            'public_key'=>$data['keys']['p256dh'],
            'auth_token'=>$data['keys']['auth'],
            'content_encoding'=>$data['contentEncoding']??'aes128gcm',
            'user_agent'=>mb_strimwidth((string)$request->userAgent(),0,500,''),
            'last_used_at'=>now(),
        ]);
        $request->session()->put('push_endpoint_hash',$hash);
        return response()->json(['ok'=>true,'id'=>$subscription->id],201);
    }

    public function destroy(Request $request)
    {
        $data=$request->validate(['endpoint'=>'required|string|max:4000']);
        $hash=hash('sha256',$data['endpoint']);
        PushSubscription::where('user_id',$request->user()->id)->where('endpoint_hash',$hash)->delete();
        if($request->session()->get('push_endpoint_hash')===$hash)$request->session()->forget('push_endpoint_hash');
        return response()->json(['ok'=>true]);
    }

    public function test(Request $request)
    {
        abort_unless(app(WebPushService::class)->configured(),503,'Push-уведомления ещё не настроены на сервере');
        abort_if(PushSubscription::where('user_id',$request->user()->id)->doesntExist(),422,'На аккаунте нет активных push-подписок');
        $notification=CrmNotification::create([
            'user_id'=>$request->user()->id,
            'type'=>'push_test',
            'title'=>'Тестовое push-уведомление',
            'body'=>'Push CRM ЗЦРБ работает на этом устройстве.',
            'url'=>'/',
        ]);
        return response()->json(['ok'=>true,'notification_id'=>$notification->id]);
    }
}
