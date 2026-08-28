<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;

class TenantManifestController extends Controller
{
    public function show(Request $request)
    {
        $code=strtolower(trim((string)$request->query('organization')));
        $org=Organization::where('code',$code)->where('is_active',true)->firstOrFail();
        $icon=$org->icon_path ?: '/pwa-icon.svg';
        return response()->json([
            'name'=>$org->name,
            'short_name'=>$org->short_name ?: $org->name,
            'description'=>'Корпоративная CRM — '.$org->name,
            'start_url'=>'/',
            'scope'=>'/',
            'display'=>'standalone',
            'background_color'=>'#f4f6f9',
            'theme_color'=>$org->primary_color ?: '#0d6efd',
            'lang'=>'ru',
            'icons'=>[
                ['src'=>$icon,'sizes'=>'any','type'=>$this->mimeFor($icon),'purpose'=>'any maskable'],
            ],
            'shortcuts'=>[
                ['name'=>'Задачи','url'=>'/tasks'],
                ['name'=>'Календарь','url'=>'/calendar'],
                ['name'=>'Поиск','url'=>'/search'],
            ],
        ],200,['Content-Type'=>'application/manifest+json; charset=UTF-8','Cache-Control'=>'private, max-age=300']);
    }

    private function mimeFor(string $path): string
    {
        return match(strtolower(pathinfo(parse_url($path,PHP_URL_PATH)?:$path,PATHINFO_EXTENSION))){'png'=>'image/png','jpg','jpeg'=>'image/jpeg','webp'=>'image/webp',default=>'image/svg+xml'};
    }
}
