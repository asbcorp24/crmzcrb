<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskTemplate extends Model
{
    use BelongsToOrganization;
    protected $fillable = ['organization_id','created_by','assigned_to','project_id','basis_id','responsible_department_id','start_after_days','customer_type','customer_id','business_status_id','add_to_plan','title','description','priority','due_after_days','recurrence','recurrence_interval','weekday','day_of_month','next_run_at','is_active'];
    protected $casts = ['start_after_days'=>'integer','due_after_days'=>'integer','next_run_at'=>'datetime','is_active'=>'boolean','add_to_plan'=>'boolean'];
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class,'assigned_to'); }
    public function checklistItems(): HasMany { return $this->hasMany(TaskTemplateChecklistItem::class)->orderBy('sort_order'); }
}
