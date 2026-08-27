<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityComment extends Model
{
    protected $fillable = ['entity_type','entity_id','user_id','body'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
