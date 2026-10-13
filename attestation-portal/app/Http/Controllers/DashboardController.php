<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Test;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user=$request->user();
        if($user->isSuperAdmin()) return redirect()->route('admin.dashboard');

        $tests=Test::with('workshops')
            ->where('status','active')
            ->whereHas('workshops',fn($q)=>$q->where('workshops.id',$user->workshop_id))
            ->where(fn($q)=>$q->whereNull('opens_at')->orWhere('opens_at','<=',now()))
            ->where(fn($q)=>$q->whereNull('closes_at')->orWhere('closes_at','>=',now()))
            ->orderByDesc('id')->get();

        $attempts=Attempt::where('user_id',$user->id)->orderByDesc('id')->get()->groupBy('test_id');
        $campaigns=DB::table('attestation_campaigns')
            ->join('attestation_campaign_workshop','attestation_campaign_workshop.campaign_id','=','attestation_campaigns.id')
            ->where('attestation_campaign_workshop.workshop_id',$user->workshop_id)
            ->where('attestation_campaigns.is_active',true)
            ->select('attestation_campaigns.*')->distinct()->orderByDesc('id')->get();

        return view('dashboard',compact('tests','attempts','campaigns'));
    }
}
