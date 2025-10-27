<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class Address extends Model
{
    protected $primaryKey = 'address_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'address_id', 'country', 'region', 'province',
        'city_municipality', 'barangay', 'street', 'zip_code'
    ];

    //creating a custom primary key generator for user_id
    use GeneratesCustomId;

    // Customize trait settings
    protected $customIdColumn = 'address_id';
    protected $customIdPrefix = 'ADR_';
    protected $customIdPadLength = 4;

    // Boot the trait
    protected static function boot()
    {
        parent::boot();
        static::bootGeneratesCustomId();
    }

    //Eloquent Relationship

    public function personalData()
    {
        return $this->hasMany(PersonalData::class, 'address_id', 'address_id');
    }
}
