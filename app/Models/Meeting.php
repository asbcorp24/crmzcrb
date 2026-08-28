<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    use BelongsToOrganization;
    protected $fillable = ['organization_id','title','held_at','location','chairman_id','secretary_id','created_by','notes','status','archived_at','archived_by'];
    protected $casts = ['held_at'=>'datetime','archived_at'=>'datetime'];
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function chairman(): BelongsTo { return $this->belongsTo(User::class,'chairman_id'); }
    public function secretary(): BelongsTo { return $this->belongsTo(User::class,'secretary_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function archivedBy(): BelongsTo { return $this->belongsTo(User::class,'archived_by'); }
    public function participants(): BelongsToMany { return $this->belongsToMany(User::class,'meeting_participants')->withTimestamps(); }
    public function items(): HasMany { return $this->hasMany(MeetingItem::class)->orderBy('number'); }
}
