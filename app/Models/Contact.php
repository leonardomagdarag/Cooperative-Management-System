<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn(mixed $value) => User::normalizeEmail($value),
        );
    }

    //Eloquent Relationships

    public function personalData()
    {
        return $this->hasOne(PersonalData::class, 'contact_id');
    }
}
