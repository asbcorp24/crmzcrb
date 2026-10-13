<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function loginPage(){ return view('auth.login'); }

    public function login(Request $request)
    {
        $data=$request->validate(['login'=>'required|string','password'=>'required|string']);
        if(!Auth::attempt(['login'=>$data['login'],'password'=>$data['password'],'is_active'=>1], $request->boolean('remember'))){
            throw ValidationException::withMessages(['login'=>'Неверный логин или пароль.']);
        }
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
