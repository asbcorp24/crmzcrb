<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable, BelongsToOrganization;

    protected $fillable = ['organization_id','department_id','manager_id','last_name','first_name','middle_name','position','email','phone','role','is_superadmin','is_active','employment_date','password','archived_at','archived_by'];
    protected $hidden = ['password','remember_token'];
    protected $casts = ['is_superadmin'=>'boolean','is_active'=>'boolean','employment_date'=>'date','email_verified_at'=>'datetime','archived_at'=>'datetime'];

    public function getFullNameAttribute(): string { return trim("{$this->last_name} {$this->first_name} {$this->middle_name}"); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function manager(): BelongsTo { return $this->belongsTo(self::class, 'manager_id'); }
    public function archivedBy(): BelongsTo { return $this->belongsTo(self::class, 'archived_by'); }
    public function subordinates(): HasMany { return $this->hasMany(self::class, 'manager_id'); }
    public function plans(): HasMany { return $this->hasMany(Plan::class); }
    public function assignedTasks(): HasMany { return $this->hasMany(Task::class, 'assigned_to'); }
    public function createdTasks(): HasMany { return $this->hasMany(Task::class, 'created_by'); }
    public function assignments(): HasMany { return $this->hasMany(EmployeeAssignment::class); }
    public function activeAssignments(): HasMany { return $this->hasMany(EmployeeAssignment::class)->whereNull('ended_at'); }

    public function isSuperAdmin(): bool { return (bool)$this->is_superadmin; }
    public function isAdmin(): bool { return !$this->is_superadmin && $this->role === 'admin'; }
    public function isManager(): bool { return !$this->is_superadmin && in_array($this->role, ['admin','manager'], true); }
}
