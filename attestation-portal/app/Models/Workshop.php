<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Workshop extends Model
{
    protected $fillable=['name','code','is_active'];
    protected $casts=['is_active'=>'boolean'];
    public function users(){return $this->hasMany(User::class);}
    public function tests(){return $this->belongsToMany(Test::class)->withTimestamps();}
}
