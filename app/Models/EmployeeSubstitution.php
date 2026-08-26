<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSubstitution extends Model
{
    protected $fillable = ['absent_user_id','substitute_user_id','date_from','date_to','comment','created_by'];
    protected $casts = ['date_from'=>'date','date_to'=>'date'];

    public function absentUser(): BelongsTo { return $this->belongsTo(User::class, 'absent_user_id'); }
    public function substituteUser(): BelongsTo { return $this->belongsTo(User::class, 'substitute_user_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
