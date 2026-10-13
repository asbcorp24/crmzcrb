<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Test extends Model
{
    protected $fillable=['created_by','title','description','instructions','kind','status','is_anonymous','time_limit_minutes','attempts_limit','pass_percent','opens_at','closes_at'];
    protected $casts=['is_anonymous'=>'boolean','opens_at'=>'datetime','closes_at'=>'datetime'];
    public function workshops(){return $this->belongsToMany(Workshop::class)->withTimestamps();}
    public function questions(){return $this->hasMany(Question::class)->orderBy('sort_order')->orderBy('id');}
    public function attempts(){return $this->hasMany(Attempt::class);}
}
