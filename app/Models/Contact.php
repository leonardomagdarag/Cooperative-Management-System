<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;
    
    public $timestamps = false;

    protected $fillable = [
        'phone_no',
        'phone_verified_at',
        'email',
        'email_verified_at',
        'facebook'
    ];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    //Eloquent Relationships

    public function personalData()
    {
        return $this->hasMany(PersonalData::class);
    }
}
