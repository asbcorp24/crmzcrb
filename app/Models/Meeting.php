<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    protected $fillable = ['title','held_at','location','chairman_id','secretary_id','created_by','notes','status'];
    protected $casts = ['held_at'=>'datetime'];

    public function chairman(): BelongsTo { return $this->belongsTo(User::class, 'chairman_id'); }
    public function secretary(): BelongsTo { return $this->belongsTo(User::class, 'secretary_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function participants(): BelongsToMany { return $this->belongsToMany(User::class, 'meeting_participants')->withTimestamps(); }
    public function items(): HasMany { return $this->hasMany(MeetingItem::class)->orderBy('number'); }
}
