<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['user_id','created_by','title','description','period_start','period_end','period_type','status','progress'];
    protected $casts = ['period_start'=>'date','period_end'=>'date'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
}
