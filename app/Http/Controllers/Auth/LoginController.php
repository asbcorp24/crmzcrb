<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function show() { return view('auth.login'); }

    public function login(Request $request)
    {
        $data=$request->validate(['organization_code'=>'nullable|string|max:50','email'=>'required|email','password'=>'required|string']);
        $email=Str::lower($data['email']); $code=Str::lower(trim((string)($data['organization_code']??'')));

        if($code==='') {
            $user=User::withoutGlobalScopes()->whereNull('organization_id')->where('is_superadmin',true)->where('email',$email)->where('is_active',true)->first();
        } else {
            $org=Organization::where('code',$code)->where('is_active',true)->first();
            if(!$org) return back()->withErrors(['organization_code'=>'Организация не найдена или отключена.'])->withInput($request->only('organization_code','email'));
            $user=User::withoutGlobalScopes()->where('organization_id',$org->id)->where('email',$email)->where('is_active',true)->where('is_superadmin',false)->first();
        }

        if($user && Hash::check($data['password'],$user->password)) {
            Auth::login($user,$request->boolean('remember'));
            $request->session()->regenerate();
            return $user->isSuperAdmin()?redirect()->route('superadmin.organizations.index'):redirect()->intended(route('dashboard'));
        }

        return back()->withErrors(['email'=>'Неверный код организации, логин или пароль.'])->withInput($request->only('organization_code','email'));
    }

    public function logout(Request $request)
    {
        $userId=$request->user()?->id; $endpointHash=$request->session()->get('push_endpoint_hash');
        if($userId&&$endpointHash) PushSubscription::where('user_id',$userId)->where('endpoint_hash',$endpointHash)->delete();
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
