<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function page(Request $request){return view('departments.index');}
    public function index(Request $request){$access=app(AccessService::class);$ids=$access->departmentIds($request->user());return response()->json(Department::withCount('users')->whereIn('id',$ids)->orderBy('sort_order')->orderBy('name')->get());}

    public function store(Request $request)
    {
        abort_unless($request->user()->isManager(),403); $orgId=(int)$request->user()->organization_id;
        $data=$request->validate([
            'parent_id'=>['nullable',Rule::exists('departments','id')->where(fn($q)=>$q->where('organization_id',$orgId))],
            'name'=>'required|string|max:190','short_name'=>'nullable|string|max:100','type'=>['required',Rule::in(['administration','department','service','office','other'])],'is_active'=>'required|boolean','sort_order'=>'nullable|integer|min:0|max:10000'
        ]);
        if(!$request->user()->isAdmin()&&!empty($data['parent_id']))abort_unless(app(AccessService::class)->departmentIds($request->user())->contains((int)$data['parent_id']),403);
        $data['organization_id']=$orgId; $department=Department::create($data); return response()->json(['ok'=>true,'department'=>$department],201);
    }

    public function update(Request $request, Department $department)
    {
        abort_unless($request->user()->isManager(),403); $access=app(AccessService::class); abort_unless($request->user()->isAdmin()||$access->canViewDepartment($request->user(),$department),403); $orgId=(int)$request->user()->organization_id;
        $data=$request->validate([
            'parent_id'=>['nullable',Rule::exists('departments','id')->where(fn($q)=>$q->where('organization_id',$orgId))],
            'name'=>'sometimes|required|string|max:190','short_name'=>'nullable|string|max:100','type'=>['sometimes','required',Rule::in(['administration','department','service','office','other'])],'is_active'=>'sometimes|boolean','sort_order'=>'nullable|integer|min:0|max:10000'
        ]);
        if(array_key_exists('parent_id',$data)&&$data['parent_id']){abort_if((int)$data['parent_id']===(int)$department->id,422,'Подразделение не может быть родителем само себе');abort_if($this->wouldCreateCycle($department->id,(int)$data['parent_id']),422,'Нельзя переместить подразделение внутрь его дочерней ветки');if(!$request->user()->isAdmin())abort_unless($access->departmentIds($request->user())->contains((int)$data['parent_id']),403);}
        $department->update($data); return response()->json(['ok'=>true,'department'=>$department->fresh()]);
    }

    private function wouldCreateCycle(int $departmentId,int $newParentId):bool{$cursor=$newParentId;$seen=[];while($cursor){if($cursor===$departmentId||isset($seen[$cursor]))return true;$seen[$cursor]=true;$cursor=(int)(Department::whereKey($cursor)->value('parent_id')?:0);}return false;}
}
