<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityAttachment extends Model
{
    protected $fillable = ['entity_type','entity_id','user_id','original_name','stored_name','path','mime_type','size'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
