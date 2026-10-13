<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttestationController extends Controller
{
    public function index(Request $request)
    {
        $user=$request->user();
        $campaigns=DB::table('attestation_campaigns')->orderByDesc('id')->get();
        $selected=$campaigns->firstWhere('id',(int)$request->query('campaign')) ?: $campaigns->first();

        $colleagues=collect();
        $myScores=collect();
        $commissionTargets=collect();
        $myCommissionScores=collect();
        $isCommission=false;

        if($selected){
            $assigned=DB::table('attestation_campaign_workshop')->where('campaign_id',$selected->id)->pluck('workshop_id')->map(fn($v)=>(int)$v);
            if($user->workshop_id && $assigned->contains((int)$user->workshop_id)){
                $colleagues=User::where('workshop_id',$user->workshop_id)->where('is_active',true)->where('id','<>',$user->id)->orderBy('full_name')->get();
                $myScores=DB::table('attestation_scores')->where('campaign_id',$selected->id)->where('evaluator_id',$user->id)->pluck('score','evaluatee_id');
            }
            $isCommission=DB::table('attestation_commission_members')->where('campaign_id',$selected->id)->where('user_id',$user->id)->exists();
            if($isCommission){
                $workshopId=(int)($request->query('workshop') ?: $assigned->first());
                if($assigned->contains($workshopId)){
                    $commissionTargets=User::where('workshop_id',$workshopId)->where('is_active',true)->orderBy('full_name')->get();
                }
                $myCommissionScores=DB::table('attestation_commission_scores')->where('campaign_id',$selected->id)->where('commission_member_id',$user->id)->pluck('score','evaluatee_id');
            }
        }

        return view('attestation.index',[
            'campaigns'=>$campaigns,'selected'=>$selected,'colleagues'=>$colleagues,'myScores'=>$myScores,
            'commissionTargets'=>$commissionTargets,'myCommissionScores'=>$myCommissionScores,'isCommission'=>$isCommission,
            'workshops'=>Workshop::where('is_active',true)->orderBy('name')->get(),
        ]);
    }

    public function admin()
    {
        return view('admin.attestation',[
            'campaigns'=>DB::table('attestation_campaigns')->orderByDesc('id')->get(),
            'workshops'=>Workshop::where('is_active',true)->orderBy('name')->get(),
            'users'=>User::where('is_active',true)->where('role','employee')->orderBy('full_name')->get(),
        ]);
    }

    public function storeCampaign(Request $request)
    {
        $data=$request->validate([
            'title'=>'required|string|max:255','description'=>'nullable|string','starts_at'=>'nullable|date',
            'ends_at'=>'nullable|date|after_or_equal:starts_at','workshop_ids'=>'required|array|min:1','workshop_ids.*'=>'integer|exists:workshops,id',
            'commission_member_ids'=>'nullable|array','commission_member_ids.*'=>'integer|exists:users,id',
        ]);
        DB::transaction(function() use($data,$request){
            $id=DB::table('attestation_campaigns')->insertGetId([
                'created_by'=>$request->user()->id,'title'=>$data['title'],'description'=>$data['description']??null,
                'starts_at'=>$data['starts_at']??null,'ends_at'=>$data['ends_at']??null,'is_active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach(array_unique($data['workshop_ids']) as $wid) DB::table('attestation_campaign_workshop')->insert(['campaign_id'=>$id,'workshop_id'=>$wid,'created_at'=>now(),'updated_at'=>now()]);
            foreach(array_unique($data['commission_member_ids']??[]) as $uid) DB::table('attestation_commission_members')->insert(['campaign_id'=>$id,'user_id'=>$uid,'created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success','Кампания аттестации создана.');
    }

    public function savePeer(Request $request, int $campaign)
    {
        $user=$request->user();
        abort_unless($user->workshop_id,422);
        abort_unless(DB::table('attestation_campaign_workshop')->where('campaign_id',$campaign)->where('workshop_id',$user->workshop_id)->exists(),403);
        $data=$request->validate(['scores'=>'required|array','scores.*'=>'nullable|integer|min:1|max:4']);
        $valid=User::where('workshop_id',$user->workshop_id)->where('is_active',true)->where('id','<>',$user->id)->pluck('id')->map(fn($v)=>(int)$v)->flip();
        foreach($data['scores'] as $id=>$score){
            if($score===null||$score==='') continue;
            abort_unless($valid->has((int)$id),422);
            DB::table('attestation_scores')->updateOrInsert(
                ['campaign_id'=>$campaign,'evaluator_id'=>$user->id,'evaluatee_id'=>(int)$id],
                ['workshop_id'=>$user->workshop_id,'score'=>(int)$score,'created_at'=>now(),'updated_at'=>now()]
            );
        }
        return back()->with('success','Оценки сохранены.');
    }

    public function saveCommission(Request $request, int $campaign)
    {
        $user=$request->user();
        abort_unless(DB::table('attestation_commission_members')->where('campaign_id',$campaign)->where('user_id',$user->id)->exists(),403);
        $data=$request->validate(['scores'=>'required|array','scores.*'=>'nullable|integer|min:1|max:4','comments'=>'nullable|array']);
        $assigned=DB::table('attestation_campaign_workshop')->where('campaign_id',$campaign)->pluck('workshop_id');
        $valid=User::whereIn('workshop_id',$assigned)->where('is_active',true)->pluck('id')->map(fn($v)=>(int)$v)->flip();
        foreach($data['scores'] as $id=>$score){
            if($score===null||$score==='') continue;
            abort_unless($valid->has((int)$id),422);
            DB::table('attestation_commission_scores')->updateOrInsert(
                ['campaign_id'=>$campaign,'commission_member_id'=>$user->id,'evaluatee_id'=>(int)$id],
                ['score'=>(int)$score,'comment'=>trim((string)($data['comments'][$id]??''))?:null,'created_at'=>now(),'updated_at'=>now()]
            );
        }
        return back()->with('success','Оценки комиссии сохранены.');
    }
}
