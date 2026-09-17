<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionMeeting extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id','title','protocol_number','held_at','chairman_id','secretary_id',
        'created_by','status','notes','protocol_generated_at',
    ];

    protected $casts = [
        'held_at' => 'datetime',
        'protocol_generated_at' => 'datetime',
    ];

    public function chairman(): BelongsTo { return $this->belongsTo(User::class, 'chairman_id'); }
    public function secretary(): BelongsTo { return $this->belongsTo(User::class, 'secretary_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany { return $this->hasMany(ProductionMeetingItem::class)->orderBy('number'); }
}
