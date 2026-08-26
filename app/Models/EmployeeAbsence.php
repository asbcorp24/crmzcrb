<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAbsence extends Model
{
    protected $fillable = ['user_id','type','date_from','date_to','document_number','comment','created_by'];
    protected $casts = ['date_from'=>'date','date_to'=>'date'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
