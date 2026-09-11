<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserSettingsController extends Controller
{
    public function page(Request $request)
    {
        return view('settings.user', [
            'currentTheme' => $request->user()->ui_theme ?: 'light',
            'themes' => $this->themes(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'ui_theme' => ['required', Rule::in(array_keys($this->themes()))],
        ]);

        $request->user()->update(['ui_theme' => $data['ui_theme']]);

        return back()->with('success', 'Тема интерфейса сохранена.');
    }

    public function theme(Request $request)
    {
        $theme = $request->user()->ui_theme ?: 'light';
        if (!array_key_exists($theme, $this->themes())) $theme = 'light';

        return response()->json(['theme' => $theme]);
    }

    private function themes(): array
    {
        return [
            'light' => ['name'=>'Светлая','description'=>'Классический светлый интерфейс.'],
            'dark' => ['name'=>'Тёмная','description'=>'Тёмный фон для работы вечером.'],
            'blue' => ['name'=>'Синяя','description'=>'Светлая тема с насыщенными синими акцентами.'],
            'green' => ['name'=>'Зелёная','description'=>'Спокойные зелёные акценты и мягкий фон.'],
            'purple' => ['name'=>'Фиолетовая','description'=>'Фиолетовые акценты и современное оформление.'],
            'contrast' => ['name'=>'Контрастная','description'=>'Повышенный контраст текста, границ и элементов управления.'],
        ];
    }
}
