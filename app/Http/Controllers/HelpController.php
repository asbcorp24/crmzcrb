<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HelpController extends Controller
{
    public function page(Request $request, ?string $section = null)
    {
        $sections = [
            'start','dashboard','tasks','plans','calendar','employees','departments','meetings',
            'availability','templates','staffing','control','reports','search','notifications','roles','faq'
        ];

        if ($section !== null && !in_array($section, $sections, true)) {
            abort(404);
        }

        return view('help.index', [
            'section' => $section,
            'sections' => $sections,
        ]);
    }
}
