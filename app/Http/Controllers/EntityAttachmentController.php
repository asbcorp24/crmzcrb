<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\EntityAttachment;
use App\Models\Meeting;
use App\Models\Plan;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EntityAttachmentController extends Controller
{
    public function index(Request $request,string $type,int $id)
    {
        $entity=$this->entity($type,$id); $this->authorizeEntity($request,$type,$entity,false);
        return response()->json(EntityAttachment::with('user')->where('entity_type',$type)->where('entity_id',$id)->latest()->get());
    }

    public function store(Request $request,string $type,int $id)
    {
        $entity=$this->entity($type,$id); $this->authorizeEntity($request,$type,$entity,true);
        $request->validate(['file'=>'required|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,csv,txt,rtf,jpg,jpeg,png,webp,zip,rar,7z']);
        $file=$request->file('file'); $stored=Str::uuid().'.'.$file->getClientOriginalExtension();
        $path=$file->storeAs('entity-attachments/'.$type.'/'.$id,$stored,'local');
        $attachment=EntityAttachment::create(['entity_type'=>$type,'entity_id'=>$id,'user_id'=>$request->user()->id,'original_name'=>$file->getClientOriginalName(),'stored_name'=>$stored,'path'=>$path,'mime_type'=>$file->getMimeType(),'size'=>$file->getSize()]);
        return response()->json(['ok'=>true,'attachment'=>$attachment->load('user')],201);
    }

    public function download(Request $request,EntityAttachment $attachment)
    {
        $entity=$this->entity($attachment->entity_type,$attachment->entity_id); $this->authorizeEntity($request,$attachment->entity_type,$entity,false);
        abort_unless(Storage::disk('local')->exists($attachment->path),404);
        return Storage::disk('local')->download($attachment->path,$attachment->original_name);
    }

    public function destroy(Request $request,EntityAttachment $attachment)
    {
        $entity=$this->entity($attachment->entity_type,$attachment->entity_id); $this->authorizeEntity($request,$attachment->entity_type,$entity,true);
        abort_unless($request->user()->isAdmin()||$request->user()->isManager()||$attachment->user_id===$request->user()->id,403);
        Storage::disk('local')->delete($attachment->path); $attachment->delete(); return response()->json(['ok'=>true]);
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
