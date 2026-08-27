<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show() { return view('auth.login'); }
    public function login(Request $request)
    {
        $credentials = $request->validate(['email'=>'required|email','password'=>'required|string']);
        if (Auth::attempt(array_merge($credentials, ['is_active'=>1]), $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }
        return back()->withErrors(['email'=>'Неверный логин, пароль или учётная запись отключена.'])->onlyInput('email');
    }
    public function logout(Request $request)
    {
        $userId=$request->user()?->id;
        $endpointHash=$request->session()->get('push_endpoint_hash');
        if($userId&&$endpointHash){
            PushSubscription::where('user_id',$userId)->where('endpoint_hash',$endpointHash)->delete();
        }
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
