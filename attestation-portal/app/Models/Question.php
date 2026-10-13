<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Question extends Model
{
    protected $fillable=['test_id','section','text','type','required','weight','sort_order','scale_min','scale_max','peer_field'];
    protected $casts=['required'=>'boolean','peer_field'=>'boolean'];
    public function test(){return $this->belongsTo(Test::class);}
    public function options(){return $this->hasMany(QuestionOption::class)->orderBy('sort_order')->orderBy('id');}
}
