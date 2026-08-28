<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::withCount(['users','departments'])->orderBy('name')->get();
        return view('superadmin.organizations.index', compact('organizations'));
    }

    public function store(Request $request)
    {
        $data=$request->validate([
            'name'=>'required|string|max:190','short_name'=>'nullable|string|max:100',
            'code'=>'required|string|max:50|alpha_dash|unique:organizations,code',
            'primary_color'=>'required|regex:/^#[0-9A-Fa-f]{6}$/','secondary_color'=>'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'timezone'=>'required|string|max:64','logo'=>'nullable|image|max:2048','icon'=>'nullable|image|max:1024',
            'admin_last_name'=>'required|string|max:100','admin_first_name'=>'required|string|max:100','admin_middle_name'=>'nullable|string|max:100',
            'admin_email'=>'required|email|max:190','admin_password'=>'required|string|min:10|max:100',
        ]);

        $org = DB::transaction(function() use($request,$data){
            $org=Organization::create([
                'name'=>$data['name'],'short_name'=>$data['short_name']??null,'code'=>Str::lower($data['code']),
                'slug'=>$this->uniqueSlug($data['name']),'primary_color'=>$data['primary_color'],'secondary_color'=>$data['secondary_color'],
                'timezone'=>$data['timezone'],'is_active'=>true,
            ]);
            $this->saveBranding($request,$org);
            $department=Department::withoutGlobalScopes()->create(['organization_id'=>$org->id,'name'=>'Администрация','short_name'=>'Администрация','type'=>'administration','is_active'=>true,'sort_order'=>0]);
            User::withoutGlobalScopes()->create([
                'organization_id'=>$org->id,'department_id'=>$department->id,'manager_id'=>null,
                'last_name'=>$data['admin_last_name'],'first_name'=>$data['admin_first_name'],'middle_name'=>$data['admin_middle_name']??null,
                'position'=>'Администратор организации','email'=>Str::lower($data['admin_email']),'role'=>'admin','is_superadmin'=>false,
                'is_active'=>true,'password'=>Hash::make($data['admin_password']),
            ]);
            return $org;
        });

        return redirect()->route('superadmin.organizations.index')->with('success','Организация «'.$org->name.'» создана.');
    }

    public function update(Request $request, Organization $organization)
    {
        $data=$request->validate([
            'name'=>'required|string|max:190','short_name'=>'nullable|string|max:100',
            'code'=>['required','string','max:50','alpha_dash',Rule::unique('organizations','code')->ignore($organization->id)],
            'primary_color'=>'required|regex:/^#[0-9A-Fa-f]{6}$/','secondary_color'=>'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'timezone'=>'required|string|max:64','logo'=>'nullable|image|max:2048','icon'=>'nullable|image|max:1024',
        ]);
        $organization->update(['name'=>$data['name'],'short_name'=>$data['short_name']??null,'code'=>Str::lower($data['code']),'primary_color'=>$data['primary_color'],'secondary_color'=>$data['secondary_color'],'timezone'=>$data['timezone']]);
        $this->saveBranding($request,$organization);
        return back()->with('success','Настройки организации сохранены.');
    }

    public function toggle(Organization $organization)
    {
        $organization->update(['is_active'=>!$organization->is_active]);
        return back()->with('success',$organization->is_active?'Организация включена.':'Организация отключена.');
    }

    private function uniqueSlug(string $name): string
    {
        $base=Str::slug($name)?:'organization'; $slug=$base; $i=2;
        while(Organization::where('slug',$slug)->exists()) $slug=$base.'-'.$i++;
        return $slug;
    }

    private function saveBranding(Request $request, Organization $organization): void
    {
        $dir=public_path('uploads/organizations/'.$organization->id);
        if(!is_dir($dir)) @mkdir($dir,0775,true);
        foreach(['logo','icon'] as $key){
            if(!$request->hasFile($key)) continue;
            $file=$request->file($key); $name=$key.'-'.time().'.'.$file->getClientOriginalExtension(); $file->move($dir,$name);
            $organization->forceFill([$key.'_path'=>'/uploads/organizations/'.$organization->id.'/'.$name])->save();
        }
    }
}
