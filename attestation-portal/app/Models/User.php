<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'workshop_id','full_name','login','password','password_encrypted','role','position','is_active'
    ];

    protected $hidden = ['password','password_encrypted','remember_token'];

    protected $casts = ['is_active'=>'boolean'];

    public function workshop(){ return $this->belongsTo(Workshop::class); }
    public function attempts(){ return $this->hasMany(Attempt::class); }

    public function isSuperAdmin(): bool { return $this->role === 'superadmin'; }

    public function setVisiblePassword(string $password): void
    {
        $this->password = bcrypt($password);
        $this->password_encrypted = Crypt::encryptString($password);
    }

    public function visiblePassword(): ?string
    {
        if (!$this->password_encrypted) return null;
        try { return Crypt::decryptString($this->password_encrypted); }
        catch (\Throwable $e) { return null; }
    }
}
