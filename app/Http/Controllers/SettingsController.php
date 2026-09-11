<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function page(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $organization = Organization::findOrFail($request->user()->organization_id);
        $settings = $organization->settings ?: [];

        return view('settings.index', [
            'organization' => $organization,
            'loginMode' => $settings['login_mode'] ?? 'email',
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'login_mode' => ['required', Rule::in(['email', 'fio'])],
        ]);

        $organization = Organization::findOrFail($request->user()->organization_id);
        $settings = $organization->settings ?: [];
        $settings['login_mode'] = $data['login_mode'];
        $organization->settings = $settings;
        $organization->save();

        return back()->with('success', 'Настройки входа сохранены.');
    }
}
