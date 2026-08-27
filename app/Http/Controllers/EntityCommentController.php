<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\EntityComment;
use App\Models\Meeting;
use App\Models\Plan;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;

class EntityCommentController extends Controller
{
    public function index(Request $request,string $type,int $id)
    {
        $entity=$this->entity($type,$id); $this->authorizeEntity($request,$type,$entity,false);
        return response()->json(EntityComment::with('user')->where('entity_type',$type)->where('entity_id',$id)->latest()->get());
    }

    public function store(Request $request,string $type,int $id)
    {
        $entity=$this->entity($type,$id); $this->authorizeEntity($request,$type,$entity,true);
        $data=$request->validate(['body'=>'required|string|max:10000']);
        $comment=EntityComment::create(['entity_type'=>$type,'entity_id'=>$id,'user_id'=>$request->user()->id,'body'=>$data['body']]);
        return response()->json(['ok'=>true,'comment'=>$comment->load('user')],201);
    }

    private function entity(string $type,int $id)
    {
        return match($type){'plan'=>Plan::findOrFail($id),'meeting'=>Meeting::findOrFail($id),'department'=>Department::findOrFail($id),'user'=>User::findOrFail($id),default=>abort(404)};
    }

    private function authorizeEntity(Request $request,string $type,$entity,bool $write): void
    {
        $user=$request->user(); if($user->isAdmin()) return; $access=app(AccessService::class);
        $ok=match($type){
            'plan'=>$access->userIds($user,true)->contains((int)$entity->user_id),
            'meeting'=>$user->isManager()&&($entity->created_by===$user->id||$access->userIds($user,true)->contains((int)$entity->created_by)||$entity->participants()->where('users.id',$user->id)->exists()),
            'department'=>$access->canViewDepartment($user,$entity),
            'user'=>$access->canViewUser($user,$entity),
            default=>false,
        };
        if($write&&$type==='department')$ok=$user->isManager()&&$ok;
        if($write&&$type==='user')$ok=$user->id===$entity->id||($user->isManager()&&$access->canManageUser($user,$entity));
        abort_unless($ok,403);
    }
}
