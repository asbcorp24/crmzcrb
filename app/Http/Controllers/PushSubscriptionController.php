<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
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
            'content_encoding'=>$data['contentEncoding']??'aesgcm',
            'user_agent'=>mb_strimwidth((string)$request->userAgent(),0,500,''),
            'last_used_at'=>now(),
        ]);
        return response()->json(['ok'=>true,'id'=>$subscription->id],201);
    }

    public function destroy(Request $request)
    {
        $data=$request->validate(['endpoint'=>'required|string|max:4000']);
        PushSubscription::where('user_id',$request->user()->id)->where('endpoint_hash',hash('sha256',$data['endpoint']))->delete();
        return response()->json(['ok'=>true]);
    }
}
