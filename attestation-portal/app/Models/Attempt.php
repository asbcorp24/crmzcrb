<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Attempt extends Model
{
    protected $fillable=['test_id','user_id','workshop_id','status','started_at','submitted_at','score','max_score','percent','passed'];
    protected $casts=['started_at'=>'datetime','submitted_at'=>'datetime','passed'=>'boolean'];
    public function test(){return $this->belongsTo(Test::class);}
    public function user(){return $this->belongsTo(User::class);}
    public function workshop(){return $this->belongsTo(Workshop::class);}
    public function answers(){return $this->hasMany(Answer::class);}
}
