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
    public function show()
    {
        return view('auth.login');
    }

    public function organizationInfo(Request $request)
    {
        $code = Str::lower(trim((string) $request->query('code', '')));
        if ($code === '') {
            return response()->json(['mode' => 'superadmin']);
        }

        $org = Organization::where('code', $code)->where('is_active', true)->first();
        if (!$org) {
            return response()->json(['message' => 'Организация не найдена или отключена.'], 404);
        }

        $settings = $org->settings ?: [];
        $mode = $settings['login_mode'] ?? 'email';
        $payload = ['mode' => $mode, 'organization' => $org->display_name];

        if ($mode === 'fio') {
            $payload['users'] = User::withoutGlobalScopes()
                ->where('organization_id', $org->id)
                ->where('is_superadmin', false)
                ->where('is_active', true)
                ->whereNull('archived_at')
                ->orderBy('last_name')->orderBy('first_name')->orderBy('middle_name')
                ->get(['id','last_name','first_name','middle_name','position'])
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->full_name,
                    'position' => $u->position,
                ])->values();
        }

        return response()->json($payload);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'organization_code' => 'nullable|string|max:50',
            'login' => 'nullable|string|max:190',
            'email' => 'nullable|email|max:190',
            'user_id' => 'nullable|integer',
            'password' => 'required|string',
        ]);

        $code = Str::lower(trim((string) ($data['organization_code'] ?? '')));
        $user = null;

        if ($code === '') {
            $envLogin = (string) config('superadmin.login', '');
            $envPassword = (string) config('superadmin.password', '');
            $enteredLogin = trim((string) ($data['login'] ?? $data['email'] ?? ''));

            if ($envLogin === '' || $envPassword === '') {
                return back()->withErrors(['login' => 'В .env не настроены SUPERADMIN_LOGIN и SUPERADMIN_PASSWORD.'])->withInput();
            }

            if (!hash_equals($envLogin, $enteredLogin) || !hash_equals($envPassword, (string) $data['password'])) {
                return back()->withErrors(['login' => 'Неверный логин или пароль суперадминистратора.'])->withInput();
            }

            $user = User::withoutGlobalScopes()
                ->whereNull('organization_id')
                ->where('is_superadmin', true)
                ->where('is_active', true)
                ->first();

            if (!$user) {
                return back()->withErrors(['login' => 'Учётная запись суперадминистратора в базе не создана. Выполните php artisan crm:superadmin.'])->withInput();
            }
        } else {
            $org = Organization::where('code', $code)->where('is_active', true)->first();
            if (!$org) {
                return back()->withErrors(['organization_code' => 'Организация не найдена или отключена.'])->withInput($request->only('organization_code'));
            }

            $settings = $org->settings ?: [];
            $mode = $settings['login_mode'] ?? 'email';

            if ($mode === 'fio') {
                $userId = (int) ($data['user_id'] ?? 0);
                if (!$userId) {
                    return back()->withErrors(['user_id' => 'Выберите сотрудника.'])->withInput($request->only('organization_code'));
                }
                $user = User::withoutGlobalScopes()
                    ->where('organization_id', $org->id)
                    ->where('id', $userId)
                    ->where('is_active', true)
                    ->where('is_superadmin', false)
                    ->whereNull('archived_at')
                    ->first();
            } else {
                $email = Str::lower(trim((string) ($data['email'] ?? $data['login'] ?? '')));
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return back()->withErrors(['email' => 'Введите корректный email.'])->withInput($request->only('organization_code','email'));
                }
                $user = User::withoutGlobalScopes()
                    ->where('organization_id', $org->id)
                    ->where('email', $email)
                    ->where('is_active', true)
                    ->where('is_superadmin', false)
                    ->first();
            }

            if (!$user || !Hash::check($data['password'], $user->password)) {
                return back()->withErrors(['password' => 'Неверный код организации, сотрудник или пароль.'])->withInput($request->only('organization_code','email','user_id'));
            }
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->put('tenant_organization_id', (int) ($user->organization_id ?? 0));
        $request->session()->put('tenant_is_superadmin', (bool) $user->is_superadmin);
        config([
            'tenant.organization_id' => (int) ($user->organization_id ?? 0),
            'tenant.is_superadmin' => (bool) $user->is_superadmin,
        ]);

        return $user->isSuperAdmin()
            ? redirect()->route('superadmin.organizations.index')
            : redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        $userId = $request->user()?->id;
        $endpointHash = $request->session()->get('push_endpoint_hash');
        if ($userId && $endpointHash) {
            PushSubscription::where('user_id', $userId)->where('endpoint_hash', $endpointHash)->delete();
        }
        $request->session()->forget(['tenant_organization_id','tenant_is_superadmin','push_endpoint_hash']);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
