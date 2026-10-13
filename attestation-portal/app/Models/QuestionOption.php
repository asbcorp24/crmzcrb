<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QuestionOption extends Model
{
    protected $fillable=['question_id','value','label','is_correct','score','sort_order'];
    protected $casts=['is_correct'=>'boolean'];
    public function question(){return $this->belongsTo(Question::class);}
}
