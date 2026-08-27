<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;

class ArchiveController extends Controller
{
    public function page(Request $request)
    {
        $u=$request->user(); $access=app(AccessService::class); $ids=$access->userIds($u,true);
        $tasks=Task::with(['assignee','creator'])->whereNotNull('archived_at')->where(function($q)use($u,$ids){if(!$u->isAdmin())$q->whereIn('assigned_to',$ids)->orWhere('created_by',$u->id);})->latest('archived_at')->limit(100)->get();
        $plans=Plan::with('user')->whereNotNull('archived_at')->whereIn('user_id',$ids)->latest('archived_at')->limit(100)->get();
        $meetings=$u->isManager()?Meeting::with('creator')->whereNotNull('archived_at')->when(!$u->isAdmin(),fn($q)=>$q->whereIn('created_by',$ids))->latest('archived_at')->limit(100)->get():collect();
        $users=$u->isManager()?User::whereNotNull('archived_at')->when(!$u->isAdmin(),fn($q)=>$q->whereIn('id',$ids))->latest('archived_at')->limit(100)->get():collect();
        return view('archive.index',compact('tasks','plans','meetings','users'));
    }

    public function store(Request $request,string $type,int $id)
    {
        abort_unless($request->user()->isManager(),403); $m=$this->resolve($type,$id); $this->authorize($request,$type,$m);
        if($type==='task') abort_unless(in_array($m->status,['completed','cancelled'],true),422,'Сначала завершите или отмените задачу');
        if($type==='user') $m->is_active=false;
        $m->archived_at=now(); $m->archived_by=$request->user()->id; $m->save(); return response()->json(['ok'=>true]);
    }

    public function restore(Request $request,string $type,int $id)
    {
        abort_unless($request->user()->isManager(),403); $m=$this->resolve($type,$id); $this->authorize($request,$type,$m);
        $m->archived_at=null; $m->archived_by=null; if($type==='user')$m->is_active=true; $m->save(); return response()->json(['ok'=>true]);
    }

    private function resolve(string $type,int $id){return match($type){'task'=>Task::findOrFail($id),'plan'=>Plan::findOrFail($id),'meeting'=>Meeting::findOrFail($id),'user'=>User::findOrFail($id),default=>abort(404)};}
    private function authorize(Request $r,string $type,$m):void{$u=$r->user();if($u->isAdmin())return;$ids=app(AccessService::class)->userIds($u,true);$ok=match($type){'task'=>$m->created_by===$u->id||$ids->contains((int)$m->assigned_to),'plan'=>$m->created_by===$u->id||$ids->contains((int)$m->user_id),'meeting'=>$m->created_by===$u->id||$ids->contains((int)$m->created_by),'user'=>app(AccessService::class)->canManageUser($u,$m),default=>false};abort_unless($ok,403);}
}
