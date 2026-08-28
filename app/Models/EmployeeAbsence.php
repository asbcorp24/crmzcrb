<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAbsence extends Model
{
    use BelongsToOrganization;
    protected $fillable = ['organization_id','user_id','type','date_from','date_to','document_number','comment','created_by'];
    protected $casts = ['date_from'=>'date','date_to'=>'date'];
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
}
