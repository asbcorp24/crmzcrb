<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Answer extends Model
{
    protected $fillable=['attempt_id','question_id','value','peer_text','score'];
    protected $casts=['value'=>'array'];
    public function attempt(){return $this->belongsTo(Attempt::class);}
    public function question(){return $this->belongsTo(Question::class);}
}
