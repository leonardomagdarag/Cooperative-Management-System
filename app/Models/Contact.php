<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class Contact extends Model
{
    protected $primaryKey = 'contact_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'contact_id',
        'phone_no',
        'phone_verified_at',
        'email',
        'email_verified_at'
    ];

    //creating a custom primary key generator for user_id
    use GeneratesCustomId;

    // Customize trait settings
    protected $customIdColumn = 'contact_id';
    protected $customIdPrefix = 'CTC_';
    protected $customIdPadLength = 4;

    // Boot the trait
    protected static function boot()
    {
        parent::boot();
        static::bootGeneratesCustomId();
    }

    //Eloquent Relationships

    public function personalData()
    {
        return $this->hasMany(PersonalData::class, 'contact_id', 'contact_id');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'contact_id', 'contact_id');
    }
}
