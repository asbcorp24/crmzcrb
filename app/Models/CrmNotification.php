<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmNotification extends Model
{
    protected $fillable = ['user_id','task_id','type','title','body','url','read_at'];
    protected $casts = ['read_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
}
